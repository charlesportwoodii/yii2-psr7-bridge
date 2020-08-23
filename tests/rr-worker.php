#!/usr/bin/env php
<?php

ini_set('display_errors', 'stderr');

// Set your normal YII_ definitions
defined('YII_DEBUG') or define('YII_DEBUG', true);
// Alternatives set this in your rr.yaml file
//defined('YII_DEBUG') or define('YII_DEBUG', \getenv('YII_DEBUG'));

defined('YII_ENV') or define('YII_ENV', 'dev');
// Alternatives set this in your rr.yaml file
//defined('YII_ENV') or define('YII_ENV', \getenv('YII_ENV'));

// Load your vendor dependencies and Yii2 autoloader
require_once '../vendor/autoload.php';
require_once '../vendor/yiisoft/yii2/Yii.php';

// Roadrunner relay and PSR7 object
$relay = new \Spiral\Goridge\StreamRelay(STDIN, STDOUT);
$psr7 = new \Spiral\RoadRunner\PSR7Client(new \Spiral\RoadRunner\Worker($relay));

// Load your configuration file
$config = require_once './config/config.php';

$application = (new \yii\Psr7\web\Application($config));

// Handle each request in a loop
while ($request = $psr7->acceptRequest()) {
    try {
        $response = $application->handle($request);
        $psr7->respond($response);
    } catch (\Throwable $e) {
        // \yii\Psr7\web\ErrorHandler should handle any exceptions
        // however you should implement your custom error handler should anything slip past.
        $psr7->getWorker()->error((string)$e);
    }

    // Workers will steadily grow in memory with each request until PHP memory_limit is reached, resulting in a worker crash.
    // With RoadRunner, you can tell the worker to shutdown if it approaches 10% of the maximum memory limit, allowing you to achieve better uptime.
    if ($application->clean()) {
        $psr7->getWorker()->stop();
        return;
    }
}