<?php

use obray\core\encoders\ConsoleEncoder;
use obray\core\encoders\CSVEncoder;
use obray\core\encoders\ErrorEncoder;
use obray\core\encoders\HTMLEncoder;
use obray\core\encoders\JSONEncoder;
use obray\core\Factory;
use obray\core\Invoker;
use obray\core\Router;

// starttime and error handling
$starttime = microtime(TRUE);
error_reporting(E_ALL);
ini_set('display_errors', true);

$loader = require_once "vendor/autoload.php";

// setup required factory, invoker, and optional container
$container = null;
$factory = new Factory($container);
$invoker = new Invoker();

// setup router
$router = new Router($factory, $invoker, $container, TRUE, $starttime);
$router->addEncoder(JSONEncoder::class,"data","application/json");
$router->addEncoder(HTMLEncoder::class,"html","text/html");
$router->addEncoder(ErrorEncoder::class,"error","application/json");
$router->addEncoder(CSVEncoder::class,"csv","text/csv");
$router->addEncoder(ConsoleEncoder::class,"console","console");

// route incoming request either through CLI or HTTP request
if( PHP_SAPI === 'cli' ){
    $response = $router->route($argv[1],array(),TRUE);
} else {
    $response = $router->route($_SERVER["REQUEST_URI"]);
}