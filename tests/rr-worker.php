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

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$worker = Spiral\RoadRunner\Worker::create();
$psrServerFactory = new Laminas\Diactoros\ServerRequestFactory();
$psrStreamFactory = new Laminas\Diactoros\StreamFactory();
$psrUploadFileFactory = new Laminas\Diactoros\UploadedFileFactory();
$psr7 = new Spiral\RoadRunner\Http\PSR7Worker($worker, $psrServerFactory, $psrStreamFactory, $psrUploadFileFactory);

$config = require __DIR__ . '/config/config.php';

$application = (new \yii\Psr7\web\Application($config));

// Handle each request in a loop
try {
    while ($request = $psr7->waitRequest()) {
        if (($request instanceof Psr\Http\Message\ServerRequestInterface)) {
            try {
                $response = $application->handle($request);
                $psr7->respond($response);
            } catch (\Throwable $e) {
                $psr7->getWorker()->error((string)$e);
            }

            if ($application->clean()) {
                $psr7->getWorker()->stop();
                return;
            }
        }
    }
} catch (\Throwable $e) {
    $psr7->getWorker()->error((string)$e);
}