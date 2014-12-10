<?php
/* ------------------------------------------------------------------------------------------------
 * Composer Autoloader
 * -----------------------------------------------------------------------------------------------*/
require_once __DIR__.'/../vendor/autoload.php';

use \Slim\Slim;
use \OpenTok\OpenTok;
use \werx\Config\Providers\ArrayProvider;
use \werx\Config\Container;
use \ServiceKit\MemcachedFactory;
use \ServiceKit\MemQueue;
use \ServiceKit\HelpSession;

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

$config->load(array('opentok', 'memcached'), true);

/* ------------------------------------------------------------------------------------------------
 * OpenTok Initialization
 * -----------------------------------------------------------------------------------------------*/
$opentok = new OpenTok($config->opentok('key'), $config->opentok('secret'));

/* ------------------------------------------------------------------------------------------------
 * Memcached Initialization
 * -----------------------------------------------------------------------------------------------*/
MemcachedFactory::configureFactory($config->memcached());
$helpQueue = new MemQueue('helpQueue');

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
$app->post('/help/session', function () use ($app, $opentok, $config) {

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

    // TODO: error checking
    HelpSession::create($session->getSessionId(), $customerName, $problemText);

    $app->response->headers->set('Content-Type', 'application/json');
    $app->response->setBody(json_encode($responseData));
});

// B) Enqueue in service queue
//    Request: (URL encoded)
//    *  `session_id`: The session which is ready to be enqueued
//
//    Response: (JSON encoded)
//    *  `queueId`: An identifier for the session's position in the queue
$app->post('/help/queue', function () use ($app, $helpQueue) {

    $sessionId = $app->request->params('session_id');

    // Validation
    // Check to see that the sessionId exists
    $helpSession = HelpSession::findBySessionId($sessionId);
    if ($helpSession == false) {
        $app->response->setStatus(400);
        $app->response->setBody('An invalid session_id was given.');
        return;
    }

    $responseData = array(
        'queueId' => $helpQueue->enqueue($helpSession)
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
$app->delete('/help/queue', function () use ($app, $helpQueue, $opentok, $config) {
    $helpSession = $helpQueue->dequeue();

    if ($helpSession) {

        $responseData = array(
            'apiKey' => $config->opentok('key'),
            'sessionId' => $helpSession->getSessionId(),
            'token' => $opentok->generateToken($helpSession->getSessionId()),
            'customerName' => $helpSession->getCustomerName(),
            'problemText' => $helpSession->getProblemText()
        );

        // Once the help session is dequeued, we also clean it out of the storage.
        // If keeping the history of this help session is important, we could mark it as dequeued 
        // instead. If we had authentication for the representative, then we could also mark the 
        // help session with the identity of the representative.
        // TODO: check for success
        HelpSession::deleteBySessionId($helpSession->getSessionId());

        $app->response->headers->set('Content-Type', 'application/json');
        $app->response->setBody(json_encode($responseData));

    } else {
        $app->response->setStatus(204);
    }
});

// TODO: a user should also be able to dequeue their own session


/* ------------------------------------------------------------------------------------------------
 * Application Start
 * -----------------------------------------------------------------------------------------------*/
$app->run();

