<?php
/* ------------------------------------------------------------------------------------------------
 * Composer Autoloader
 * -----------------------------------------------------------------------------------------------*/
require_once __DIR__.'/../vendor/autoload.php';

use \Slim\Slim;
use \OpenTok\OpenTok;

/* ------------------------------------------------------------------------------------------------
 * Slim Application Initialization
 * -----------------------------------------------------------------------------------------------*/
$app = new Slim(array(
    'log.enabled' => true,
    'templates.path' => '../templates'
));

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
$app->post('/help/session', function () use ($app) {
});

// B) Enqueue in service queue
$app->post('/help/queue', function () use ($app) {
});

// Representative delivers serivce
//
// Dequeue from service queue and assign to representative
$app->delete('/help/queue', function () use ($app) {
});

/* ------------------------------------------------------------------------------------------------
 * Application Start
 * -----------------------------------------------------------------------------------------------*/
$app->run();

