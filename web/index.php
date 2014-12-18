<?php
/* ------------------------------------------------------------------------------------------------
 * Composer Autoloader
 * -----------------------------------------------------------------------------------------------*/
require_once __DIR__.'/../vendor/autoload.php';

use \Slim\Slim;
use \OpenTok\OpenTok;
use \werx\Config\Providers\ArrayProvider;
use \werx\Config\Container;
use \Predis\Response\ErrorInterface as RedisErrorInterface;

/* ------------------------------------------------------------------------------------------------
 * Slim Application Initialization
 * -----------------------------------------------------------------------------------------------*/
$app = new Slim(array(
    'log.enabled' => true,
    'templates.path' => '../templates'
));

/* ------------------------------------------------------------------------------------------------
 * Configuration
 * -----------------------------------------------------------------------------------------------*/
$provider = new ArrayProvider('../config');
$config = new Container($provider);

// Environment Selection
$app->configureMode('development', function () use ($config) {
    $config->setEnvironment('development');
});

$config->load(array('opentok', 'redis'), true);

/* ------------------------------------------------------------------------------------------------
 * OpenTok Initialization
 * -----------------------------------------------------------------------------------------------*/
$opentok = new OpenTok($config->opentok('key'), $config->opentok('secret'));

/* ------------------------------------------------------------------------------------------------
 * Redis Initialization
 * -----------------------------------------------------------------------------------------------*/
$redis = new \Predis\Client($config->redis(), array('prefix' => 'servicekit:'));
// TODO: helper function for returning errors (response is not a good name everywhere)
// TODO: constants for key prefixes and key constants

/* ------------------------------------------------------------------------------------------------
 * Routing
 * -----------------------------------------------------------------------------------------------*/

// Customer landing page
$app->get('/', function () use ($app) {
    $app->render('customer.php');
});

// Representative landing page
$app->get('/rep', function () use ($app) {
    $app->render('representative.php');
});

// Customer requests service
//
// A) Create a help session
//    Request: (URL encoded)
//    *  `customer_name`: Customer's name
//    *  `problem_text`: Customer's problem description
//
//    Response: (JSON encoded)
//    *  `apiKey`: OpenTok API Key
//    *  `sessionId`: OpenTok session ID
//    *  `token`: User's token for the `sessionId`
$app->post('/help/session', function () use ($app, $opentok, $redis, $config) {

    $customerName = $app->request->params('customer_name');
    $problemText = $app->request->params('problem_text');

    // Validation
    $errorMessage;
    if (empty($customerName) || empty($problemText)) {
        $errorMessage = 'The fields customer_name and problem_text are required.';
    }
    if (strlen($customerName) > 50) {
        $errorMessage = 'The field customer_name is too long';
    }
    if (strlen($problemText) > 200) {
        $errorMessage = 'The field problem_text is too long';
    }
    if (!empty($errorMessage)) {
        $app->response->setStatus(400);
        $app->response->setBody($errorMessage);
        return;
    }

    $session = $opentok->createSession();
    $responseData = array(
        'apiKey' => $config->opentok('key'),
        'sessionId' => $session->getSessionId(),
        'token' => $session->generateToken()
    );

    // Save the help session details
    $response = $redis->hmset('helpsession:'.$session->getSessionId(),
        'customerName', $customerName,
        'problemText', $problemText,
        'sessionId', $session->getSessionId()
    );
    // Handle errors
    if ($response instanceof RedisErrorInterface) {
        $app->response->setStatus(500);
        $app->response->setBody('Could not create the help session\n' . (string)$response);
        return;
    }

    $app->response->headers->set('Content-Type', 'application/json');
    $app->response->setBody(json_encode($responseData));
});

// B) Enqueue in service queue
//    Request: (URL encoded)
//    *  `session_id`: The session which is ready to be enqueued
//
//    Response: (JSON encoded)
//    *  `queueId`: An identifier for the session's position in the queue
$app->post('/help/queue', function () use ($app, $redis) {

    $sessionId = $app->request->params('session_id');

    $helpSessionKey = 'helpsession:'.$sessionId;

    // Validation
    // Check to see that the help session exists
    $response = $redis->exists($helpSessionKey);
    if ($response instanceof RedisErrorInterface) {
        $app->response->setStatus(500);
        $app->response->setBody('Could not check for existence of help session.\n' . (string)$response);
        return;
    }
    $exists = (bool)$response;
    if (!$exists) {
        $app->response->setStatus(400);
        $app->response->setBody('An invalid session_id was given.');
        return;
    }

    // Add the help session to the queue
    $response = $redis->rpush('helpqueue', $helpSessionKey);
    if ($response instanceof RedisErrorInterface) {
        $app->response->setStatus(500);
        $app->response->setBody('Could not add help session to the help queue.\n' . (string)$response);
        return;
    }
    $queueId = $helpSessionKey;

    $responseData = array(
        'queueId' => $queueId
    );

    $app->response->headers->set('Content-Type', 'application/json');
    $app->response->setBody(json_encode($responseData));

});

// Representative delivers serivce
//
// Dequeue from service queue and assign to representative (FIFO). If there is a customer on the
// queue, respond with the help session data and additional data needed to connect. If there isn't
// a customer available on the queue, respond with status code 204 NO CONTENT.
//
// Response: (JSON encoded)
// *  `apiKey`:
// *  `sessionId`:
// *  `token`:
// *  `customerName`:
// *  `problemText`:
//
// NOTE: This request allows anonymous access, but if user authentication is required then the 
// identity of the request should be verified (often times with session cookies) before a valid 
// response is given.
$app->delete('/help/queue', function () use ($app, $redis, $opentok, $config) {

    // Dequeue the next help session
    $response = $redis->lpop('helpqueue');
    if ($response instanceof RedisErrorInterface) {
        $app->response->setStatus(500);
        $app->response->setBody('Could not dequeue a help session from the help queue.\n' . (string)$response);
        return;
    }
    $helpSessionKey = $response;

    // TODO: check what the return of nil looks like when the queue is empty
    if (empty($helpSessionKey)) {

        // The queue was empty
        $app->response->setStatus(204);

    } else {

        $response = $redis->hgetall($helpSessionKey);
        if ($response instanceof RedisErrorInterface) {
            $app->response->setStatus(500);
            $app->response->setBody('Could not read the help session.\n' . (string)$response);
            return;
        }
        $helpSessionData = $response;

        $responseData = array(
            'apiKey' => $config->opentok('key'),
            'sessionId' => $helpSessionData['sessionId'],
            'token' => $opentok->generateToken($helpSessionData['sessionId']),
            'customerName' => $helpSessionData['customerName'],
            'problemText' => $helpSessionData['problemText']
        );

        // Once the help session is dequeued, we also clean it out of the storage.
        // If keeping the history of this help session is important, we could mark it as dequeued 
        // instead. If we had authentication for the representative, then we could also mark the 
        // help session with the identity of the representative.
        $response = $redis->del($helpSessionKey);
        if ($response instanceof RedisErrorInterface) {
            $app->response->setStatus(500);
            $app->response->setBody('Could not delete the help session after dequeuing.\n' . (string)$response);
            return;
        }

        $app->response->headers->set('Content-Type', 'application/json');
        $app->response->setBody(json_encode($responseData));

    }
});

// Customer dequeues by cancelling or leaving the page
//
// Dequeue the specific help session from the help queue.
$app->delete('/help/queue/:queueId', function ($queueId) use ($app, $redis) {
    $response = $redis->lrem('helpqueue', 0, $queueId);
    if ($response instanceof RedisErrorInterface) {
        $app->response->setStatus(500);
        $app->response->setBody('Could not remove the help session from the queue.\n' . (string)$response);
        return;
    }

    $helpSessionKey = $queueId;
    $response = $redis->del($helpSessionKey);
    if ($response instanceof RedisErrorInterface) {
        $app->response->setStatus(500);
        $app->response->setBody('Could not delete the help session after removing from queue.\n' . (string)$response);
        return;
    }

    $app->response->setStatus(204);
});



/* ------------------------------------------------------------------------------------------------
 * Application Start
 * -----------------------------------------------------------------------------------------------*/
$app->run();

