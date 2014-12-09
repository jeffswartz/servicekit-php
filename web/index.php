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

// Representative logs in
$app->post('/login', function () use ($app) {
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

// TODO: a user should also be able to dequeue their own session

// Representative delivers serivce
//
// Dequeue from service queue and assign to representative
$app->delete('/help/queue', function () use ($app) {
});

/* ------------------------------------------------------------------------------------------------
 * Application Start
 * -----------------------------------------------------------------------------------------------*/
$app->run();

