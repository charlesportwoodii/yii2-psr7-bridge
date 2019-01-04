# Yii2 PSR7 Bridge

A PSR7 bridge for Yii2 web applications.

The usecase for this bridge is to enable Yii2 to be utilized with PSR-7 and PSR-15 middlewars and task runners such as RoadRunner and PHP-PM, with _minimal_ code changes to your application (eg requiring no changes to any calls to `Yii::$app->request` and `Yii::$app->response` within your application).

> Note that this is currently very alpha quality. It "works" in that a PSR7 request is accepted as input and it returns a valid PSR7 response that is _mostly_ in line with what you would expect.

> However, features and functionality are missing. Many things don't work, others have unexpectes side-effects. You are advised _not_ to use this package at this time.

> See the `Current Status` checklist at the bottom of the README file for what is current implemented and what we could use help with.

## What to help out?

Contributors are welcome! Check out the `Current Status` checklist for things that still need to be implemented, help add tests, or add new features!

## Installation

This package can be installed via Composer:

```
composer require charlesportwoodii/yii2-psr7-bridge
```

## Tests

Tests can be run with `phpunit`.

```bash
./vendor/bin/phpunit
```

## Usage

Due to the nature of this package, several changes are needed to your application.

1. In you `web/index.php` file, reconfigure your `Application` component as follows:

Replace your `Application` bootstrap code:

```php
(new yii\web\Application($config))->run();
```

with the following `Application` component.

```php
(new yii\Psr7\web\Application($config, $psr7Request))->handlePsr7Request();
```

2. Modify your `request` and `response` components within your web application config to be instance of `yii\Psr7\web\Request` and `yii\Psr7\web\Response`, respectively:

```php
return [
    // Other flags
    'components' => [
        'request' => [
            'class' => \yii\Psr7\web\Request::class,
            'request' => null
        ],
        'response' => [
            'class' => \yii\Psr7\web\Response::class
        ],
        // Other components
    ]
];
```

3. Run your application with a PSR7 loader.

For example, to Go/Roadrunner, you can use the component as follows:

```php
#!/usr/bin/env php
<?php

ini_set('display_errors', 'stderr');
// Load your standard component vendors
require_once __DIR__ . '/../vendor/autoload.php';

// You MUST load the Yii class manually since Yii2 has a custom autoloader
require_once __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

// Roadrunner relay and PSR7 object
$relay = new Spiral\Goridge\StreamRelay(STDIN, STDOUT);
$psr7 = new Spiral\RoadRunner\PSR7Client(new Spiral\RoadRunner\Worker($relay));

// Set your normal YII_ definitions
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

// Load your configuration file
$config = require_once __DIR__ . '/config/config.php';

// Loop
while ($request = $psr7->acceptRequest()) {
    try {
        // Create a new instance of the application
        $application = (new \yii\Psr7\web\Application($config, $request));

        $response = $application->handlePsr7Request();

        // Do any other middleware work here

        // Send a PSR7 response back to go/roadrunner
        $psr7->respond($response);
    } catch (\Throwable $e) {
        // Handle any errors sent from upstream. Ideally with Yii2, you should catch any errors before
        // Your application gets to this point via ErrorHandler
        $psr7->getWorker()->error((string)$e);
    }
}
```

## Why does this package exist?

#### Performance

The performance benefits of task runners such as RoadRunner and PHP-PM are extremely difficult to ignore.

While PHP has had incrimental speed improvements from 7.0 (phpng), the performance of web based PHP applications is limited by the need to rebootstrap _every single file_ with each HTTP request. While Nginx + PHP-FPM is fast, even with opcache every file has to read back into memory on each HTTP request.

PSR7 servers enable us to keep almost all of our classes and code in memory between requests, which mostly eliminates the biggest performance bottleneck.

#### PSR-7 and PSR-15 Compatability

While not strictly the goal of this project, it's becomming more and more difficult to ignore PSR-7 and PSR-15 middlewares. As the Yii2 team has punted PSR-7 compatability to Yii 2.1 or Yii 3, existing Yii2 projects cannot take advantage of a standardized request/response pattern, or chained middlewares.

From a code/time standpoint, that means you any PSR-15 middleware you want to play with needs to be adapted custom to Yii2, which is an ineffecient use of developers time, which runs contract to the `fast, secure, effecient` mantra Yii has.

## How this works

This package provides three classes within the `yii\Psr7\web` namespace, `Application`, `Request`, and `Response`, and a `Psr7ResponseTrait` trait that can be used to add PSR7 response capabilities to your existing classes that extend `yii\web\Response`.

To handle inbound requests, the `yii\Psr7\web\Application` component acts as a stand in replacement for `yii\web\Application` for use in your task runner. It's constructor takes the standard Yii2 configuration array, and a additional `ServerRequestInterface` instance. The `Application` component then instantiates a `yii\Psr7\web\Request` object using the `ServerRequestInterface` provided.

`yii\Psr7\web\Request` is a stand-in replacement for `yii\web\Request`. It's only purpose is to provide a interface between `ServerRequestInterface` and the standard `yii\web\Request` API.

Within your modules, controllers, actions, `Yii::$app->request` and `Yii::$app->response` may be used normally without any changes.

Before the application exists, it will call `getPsr7Response` on your `response` component. If you're using `yii\web\Response`, simply change your `response` component class in your application configuration to `yii\Psr7\web\Response`. If you're using a custom `Response` object, simply add the `yii\Psr7\web\traits\Psr7ResponseTrait` trait to your `Response` object that extends `yii\web\Response` to gain the necessary behaviors.

## Limitations

- File streams currently don't work (eg `yii\web\Response::sendFile`, `yii\web\Response::sendContentAsFile`, `yii\web\Response::sendStreamAsFile`)
- Performance isn't optimal as the `Application` component is manually reloaded each time.
- PHP wasn't originally designed for endless PSR7 looks. Expect memory leaks. RoadRunner or other task runners can help manage this.

## Current Status

- [x] Implement custom `Application component.
- [x] Convert a PSR7 Request into `yii\web\Request` object.
- [x] Return a simple response.
- [x] Handle `yii\web\Response::$format`.
- [x] Work with standard Yii2 formatters.
- [x] Handle `HeaderCollection`.
- [x] Handle `CookieCollection`.
- [x] Handle `yii\web\Response::$stream` and `yii\web\Response::$content`.
- [ ] Implement comparable `sendFile`.
- [ ] Implement `yii\web\Response::redirect`.
- [ ] Implement `yii\web\Response::refresh`.
- [ ] Probably more things I haven't tested yet.
- [ ] Test Coverage

-----

This project is licensed under the BSD-3-Clause license. See [LICENSE](LICENSE) for more details.