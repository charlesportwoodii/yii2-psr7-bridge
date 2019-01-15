# Yii2 PSR-7 Bridge

A PSR-7 bridge and PSR-16 adapter for Yii2 web applications.

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

1. Modify your `request` and `response` components within your web application config to be instance of `yii\Psr7\web\Request` and `yii\Psr7\web\Response`, respectively:

```php
return [
    // Other flags
    'components' => [
        'request' => [
            'class' => \yii\Psr7\web\Request::class,
        ],
        'response' => [
            'class' => \yii\Psr7\web\Response::class
        ],
        // Other components
    ]
];
```

2. Set the following environment variables to your task runner. With RoadRunner, your configuration might look as follows:

```yaml
env:
  YII_ALIAS_WEBROOT: /path/to/webroot
```

> Note that all 3 environment variables listed _must_ be defined.

3. Run your application with a PSR-15 compatible dispatcher

For example, to Go/Roadrunner, you can use the component as follows:

```php
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
require_once '/path/to/vendor/autoload.php';
require_once '/path/to/vendor/yiisoft/yii2/Yii.php';

// Roadrunner relay and PSR7 object
$relay = new \Spiral\Goridge\StreamRelay(STDIN, STDOUT);
$psr7 = new \Spiral\RoadRunner\PSR7Client(new \Spiral\RoadRunner\Worker($relay));

// Load your configuration file
$config = require_once '/path/to/config/config.php';

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
}
```

### PSR-7 and PSR-15 compatability

`\yii\Psr7\web\Application` implements PSR-15's `\Psr\Http\Server\RequestHandlerInterface` providing full PSR-15 compatability.

#### PSR-7

If your application doesn't require PSR-15 middlewares, you can simply return a PSR-7 response from the application as follows:

```php
$response = $application->handle($request);
$psr7->respond($response);
```

#### PSR-15 with middlewares/utils package

Since `\yii\Psr7\web\Application` is PSR-15 middleware compatible, you can also use it with any PSR-15 dispatcher.

In your test script you can utilize `middlewares/utils`.

```php
$response = \Middlewares\Utils\Dispatcher::run([
    // new Middleware,
    // new NextMiddleware, // and so forth...
    function($request, $next) use ($application) {
        return $application->handle($request);
    }
], $request);
$psr7->respond($response);
```

#### PSR-15 with ajgarlag/psr15-dispatcher

Another example with `ajgarlag/psr15-dispatcher` package as the dispatcher.

```php
$response = \Ajgarlag\Psr15\Dispatcher\Pipe::create([
    // new Middleware,
    // new NextMiddleware, // and so forth...
])->process($request, $application);
$psr7->respond($response);
```

### PSR-15 Middleware Filters

This package also provides the capabilities to process PSR-15 compatible middlewares on a per route basis via `yii\base\ActionFilter` extension.

#### Authentication

If your application requires a PSR-15 authentication middleware _not_ provided by an existing `yii\filters\auth\AuthInterface` class, you can use `yii\Psr7\filters\auth\MiddlewareAuth` to process your PSR-15 authentication middleware.

`\yii\Psr7\filters\auth\MiddlewareAuth` will run the authentication middleware. If a response is returned by your middleware it will send that response. Otherwise, it will look for the `attribute` specified by your authentication middleware, and run `yii\web\User::loginByAccessToken()` with the value stored in that attribute.

```php
return [[
    'class' => \yii\Psr7\filters\auth\MiddlewareAuth::class,
    'attribute' => 'attribute_with_user_identifier_populated_by_middleware',
    'middleware' => new AuthenticationMiddleware
]]
```

A trivial example with `middlewares/http-authentication` is shown as follows using the `username` attribute populated by `Middlewares\BasicAuthentication`.

```php
public function behaviors()
{
    return \array_merge(parent::behaviors(), [
        [
            'class' => \yii\Psr7\filters\auth\MiddlewareAuth::class,
            'attribute' => 'username',
            'middleware' => (new \Middlewares\BasicAuthentication([
                'username1' => 'password1',
                'username2' => 'password2'
            ]))->attribute('username')
        ]
    ];
}
```

> Note: This class should be compatible with `yii\filters\auth\CompositeAuth` as it returns `null`. Note however that the `yii\web\Response` object _will_ be populated should an HTTP status code or message be returned. If you require custom responses you should extend this class or manually trigger `handleFailure()`.

#### Other Middlewares

## Why does this package exist?

#### Performance

The performance benefits of task runners such as RoadRunner and PHP-PM are extremely difficult to ignore.

While PHP has had incrimental speed improvements from 7.0 (phpng), the performance of web based PHP applications is limited by the need to rebootstrap _every single file_ with each HTTP request. While Nginx + PHP-FPM is fast, even with opcache every file has to read back into memory on each HTTP request.

PSR7 servers enable us to keep almost all of our classes and code in memory between requests, which mostly eliminates the biggest performance bottleneck.

Be sure to check out the [Performance Comparisons](https://github.com/charlesportwoodii/yii2-psr7-bridge/wiki/Performance-Comparisons) wiki page for more information on the actual performance impact on the yii2-app-basic app.

#### PSR-7 and PSR-15 Compatability

While not strictly the goal of this project, it's becomming more and more difficult to ignore PSR-7 and PSR-15 middlewares. As the Yii2 team has punted PSR-7 compatability to Yii 2.1 or Yii 3, existing Yii2 projects cannot take advantage of a standardized request/response pattern, or chained middlewares.

From a code/time standpoint, that means you any PSR-15 middleware you want to play with needs to be adapted custom to Yii2, which is an ineffecient use of developers time, which runs contract to the `fast, secure, effecient` mantra Yii has.

## How this works

This package provides three classes within the `yii\Psr7\web` namespace, `Application`, `Request`, and `Response`, and a `Psr7ResponseTrait` trait that can be used to add PSR7 response capabilities to your existing classes that extend `yii\web\Response`.

To handle inbound requests, the `yii\Psr7\web\Application` component acts as a stand in replacement for `yii\web\Application` for use in your task runner. It's constructor takes the standard Yii2 configuration array, and a additional `ServerRequestInterface` instance. The `Application` component then instantiates a `yii\Psr7\web\Request` object using the `ServerRequestInterface` provided.

> Since `yii\web\Application::bootstrap` uses the `request` component, the request component needs to be properly constructed during the application constructor, as opposed to simply calling `$app->handleRequest($psr7Request);`

`yii\Psr7\web\Request` is a stand-in replacement for `yii\web\Request`. It's only purpose is to provide a interface between `ServerRequestInterface` and the standard `yii\web\Request` API.

Within your modules, controllers, actions, `Yii::$app->request` and `Yii::$app->response` may be used normally without any changes.

Before the application exists, it will call `getPsr7Response` on your `response` component. If you're using `yii\web\Response`, simply change your `response` component class in your application configuration to `yii\Psr7\web\Response`. If you're using a custom `Response` object, simply add the `yii\Psr7\web\traits\Psr7ResponseTrait` trait to your `Response` object that extends `yii\web\Response` to gain the necessary behaviors.

## Limitations

- File streams currently don't work (eg `yii\web\Response::sendFile`, `yii\web\Response::sendContentAsFile`, `yii\web\Response::sendStreamAsFile`)
- Performance isn't optimal as the `Application` component is manually reloaded each time.
- Yii2 leaks memory. Your PSR7 task runner should automatically restart workers that crash, so memory consumption shouldn't balloon on the server. Expect worker crashes.
- Yii2's ExitHandler or ErrorHandler doesn't _explicitly_ close connections at the end of the script or on a crash. For the time being, you are expected to handle these yourself to prevent connection leaks.

## Current Status

- [x] Implement custom `Application component.
- [x] Convert a PSR7 Request into `yii\web\Request` object.
- [x] Return a simple response.
- [x] Routing.
- [x] Handle `yii\web\Response::$format`.
- [x] Work with standard Yii2 formatters.
- [x] Handle `HeaderCollection`.
- [x] Handle `CookieCollection`.
- [x] Handle `yii\web\Response::$stream` and `yii\web\Response::$content`.
- [ ] Implement comparable `sendFile`.
- [x] Implement `yii\web\Response::redirect`.
- [x] Implement `yii\web\Response::refresh`.
- [x] GET query parameters `yii\web\Request::get()`.
- [x] POST parameters `yii\web\Request::post()`.
- [ ] `yii\web\Request::$methodParam` support.
- [x] `yii\web\Request::getAuthCredentials()`.
- [x] `yii\web\Request::loadCookies()`.
- [ ] `yii\web\ErrorHandler` implementation (partial).
- [ ] Reuse `Application` component instead of re-instantiating in each loop.
- [ ] Fix fatal memory leak under load
- [x] Get `yii-app-basic` to work.
- [ ] Test Coverage

-----

This project is licensed under the BSD-3-Clause license. See [LICENSE](LICENSE) for more details.