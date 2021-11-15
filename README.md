# Yii2 PSR-7 Bridge

A PSR-7 bridge and PSR-15 adapter for Yii2 web applications.

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

### Dispatcher

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

> If you're using a custom `Request` class, simply have it overload `yii\Psr7\web\Request` to inherit the base functionality.

2. Set the following environment variables to your task runner. With RoadRunner, your configuration might look as follows:

```yaml
env:
  YII_ALIAS_WEBROOT: /path/to/webroot
  YII_ALIAS_WEB: '127.0.0.1:8080'
```

> All environment variables _must_ be defined.

3. Run your application with a PSR-15 compatible dispatcher.

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

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$worker = Spiral\RoadRunner\Worker::create();
$psrServerFactory = new Laminas\Diactoros\ServerRequestFactory();
$psrStreamFactory = new Laminas\Diactoros\StreamFactory();
$psrUploadFileFactory = new Laminas\Diactoros\UploadedFileFactory();
$psr7 = new Spiral\RoadRunner\Http\PSR7Worker($worker, $psrServerFactory, $psrStreamFactory, $psrUploadFileFactory);

$config = require __DIR__ . '/../config/web.php';

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
```

### Worker Crash Protection

With each request PHP's memory usage will gradually increase. Calling `$application->clean()` will tell you if the current script usage is within 10% of your `memory_limit` ini set. While most workers will handle a out-of-memory crash exception, you can use this method to explicitly tell the current worker to stop and be reconstructed to avoid HTTP 500's thrown by out-of-memory issues.

### Session

This library is fully compatible with `yii\web\Session` and classes that descend from it with a few caveats.

1. The application component adds the following session ini settings at runtime. Do not overwrite these settings as they are necessary for `yii\web\Session`.
```php
ini_set('use_cookies', 'false');
ini_set('use_only_cookies', 'true');
```
2. Don't access `$application->getSession()` within your worker.

### Request

`yii\Psr7\web\Request` defines a stand-in replacement for `yii\web\Request`. To access the raw PSR-7 object, call `Yii::$app->request->getPsr7Request()`.

### Response

`yii\Psr7\web\Response` directly extends `yii\web\Response`. All functionality is implemented by `yii\Psr7\web\traits\Psr7ResponseTrait`, which you can `use` as a trait within any custom response classes. Alternatively you can directly extend `yii\Psr7\web\Response`.

### ErrorHandler

`yii\Psr7\web\ErrorHandler` implements custom error handling that is compatible with `yii\web\ErrorHandler`. `yii\Psr7\web\Application` automatically utilizes this error handler. If you have a custom error handler have it extend `yii\Psr7\web\ErrorHandler`.

Normal functionality via `errorAction` is supported. Yii2's standard error and exception pages should work out of the box.

### PSR-7 and PSR-15 compatability

`\yii\Psr7\web\Application` extends `\yii\web\Application` and implements PSR-15's `\Psr\Http\Server\RequestHandlerInterface` providing full PSR-15 compatability.

#### PSR-7

If your application doesn't require PSR-15 middlewares, you can simply return a PSR-7 response from the application as follows:

```php
$response = $application->handle($request);
$psr7->respond($response);
```

No dispatcher is necessary in this configuration.

#### PSR-15 with middlewares/utils package

Since `\yii\Psr7\web\Application` is PSR-15 middleware compatible, you can also use it with any PSR-15 dispatcher.

> This library does not implement it's own dispatcher, allowing the developer the freedom to use a PSR-15 compatible dispatcher of their choice for middleware handling.

As an example with `middlewares/utils`:

```php
$response = \Middlewares\Utils\Dispatcher::run([
    // new Middleware,
    // new NextMiddleware, // and so forth...
    function($request, $next) use ($application) {
        return $application->handle($request);
    }
], $request);

// rr response
$psr7->respond($response);
```

### PSR-15 Middleware Filters

This package also provides the capabilities to process PSR-15 compatible middlewares on a per route basis via `yii\base\ActionFilter` extension.

> Middlewares run on a per route basis via these methods aren't 100% PSR-15 compliant as they are executed in their own sandbox independent of the middlewares declared in any dispatcher. These middlewares operately solely within the context of the action filter itself. Middlewares such as `middlewares/request-time` will only meaure the time it takes to run the action filter rather the entire request. If you need these middlewares to function at a higher level chain them in your primary dispatcher, or consider using a native Yii2 ActionFilter.

#### Authentication

If your application requires a PSR-15 authentication middleware _not_ provided by an existing `yii\filters\auth\AuthInterface` class, you can use `yii\Psr7\filters\auth\MiddlewareAuth` to process your PSR-15 authentication middleware.

`\yii\Psr7\filters\auth\MiddlewareAuth` will run the authentication middleware. If a response is returned by your middleware it will send that response. Otherwise, it will look for the `attribute` specified by your authentication middleware, and run `yii\web\User::loginByAccessToken()` with the value stored in that attribute.

> Note your `yii\web\User` and `IdentityInterface` should be configured to handle the request attribute you provide it. As most authentication middlewares export a attribute with the user information, this should be used to interface back to Yii2's `IdentityInterface`.

A simple example with `middlewares/http-authentication` is shown as follows using the `username` attribute populated by `Middlewares\BasicAuthentication`.

```php
public function behaviors()
{
    return \array_merge(parent::behaviors(), [
        [
            'class' => \yii\Psr7\filters\auth\MiddlewareAuth::class,
            'attribute' => 'username',
            'middleware' => (new \Middlewares\BasicAuthentication(
                Yii::$app->user->getUsers() // Assumes your `IdentityInterface` class has a method call `getUsers()` that returns an array of username/password pairs
                /**
                 * Alternatively, just a simple array for you to map back to Yii2
                 *
                 * [
                 *     'username1' => 'password1',
                 *     'username2' => 'password2'
                 * ]
                 */
            ))->attribute('username')
        ]
    ]);
}
```

> Note: This class should be compatible with `yii\filters\auth\CompositeAuth` as it returns `null`. Note however that the `yii\web\Response` object _will_ be populated should an HTTP status code or message be returned. If you require custom responses you should extend this class or manually trigger `handleFailure()`.

#### Other Middlewares

`yii\Psr7\filters\MiddlewareActionFilter` can be used to process other PSR-15 compatible Middlewares. Each middleware listed will be executed sequentially, and the effective response of that middleware will be returned.

As an example: `middlewares/client-ip` and `middlewares/uuid` is used to return the response time of the request and a UUID.

```php
public function behaviors()
{
    return \array_merge(parent::behaviors(), [
        [
            'class' => \yii\Psr7\filters\MiddlewareActionFilter::class,
            'middlewares' => [
                // Yii::$app->request->getAttribute('client-ip') will return the client IP
                new \Middlewares\ClientIp,
                // Yii::$app->response->headers['X-Uuid'] will be set
                new \Middlewares\Uuid,
            ]
        ]
    ]);
}
```

The middleware handle also supports PSR-15 compatible closures.

```php
public function behaviors()
{
    return \array_merge(parent::behaviors(), [
        [
            'class' => \yii\Psr7\filters\MiddlewareActionFilter::class,
            'middlewares' => [
                function ($request, $next) {
                    // Yii::$app->request->getAttribute('foo') will be set to `bar`
                    // Yii::$app->response->headers['hello'] will be set to `world`
                    return $next->handle(
                        $request->withAttribute('foo', 'bar')
                    )->withHeader('hello', 'world')
                }
            ]
        ]
    ]);
}
```

Middlewares are processed sequentially either until a response is returned (such as an HTTP redirect) or all middlewares have been processed.

If a response is returned by any middleware executed, the before action filter will return false, and the resulting response will be sent to the client.

Any request attribute or header added by any previous middleware will be include in the response.

## Why does this package exist?

#### Performance

The performance benefits of task runners such as RoadRunner and PHP-PM are extremely difficult to ignore.

While PHP has had incrimental speed improvements from 7.0 (phpng), the performance of web based PHP applications is limited by the need to rebootstrap _every single file_ with each HTTP request. While Nginx + PHP-FPM is fast, even with opcache every file has to read back into memory on each HTTP request.

PSR-7 servers enable us to keep almost all of our classes and code in memory between requests, which mostly eliminates the biggest performance bottleneck.

Be sure to check out the [Performance Comparisons](https://github.com/charlesportwoodii/yii2-psr7-bridge/wiki/Performance-Comparisons) wiki page for more information on the actual performance impact on the yii2-app-basic app.

It is expected that [PHP 7.4 preloading](https://wiki.php.net/rfc/preload) would improve performance further.

#### PSR-7 and PSR-15 Compatability

While not strictly the goal of this project, it's becomming more and more difficult to ignore PSR-7 and PSR-15 middlewares. As the Yii2 team has punted PSR-7 compatability to Yii 2.1 or Yii 3, existing Yii2 projects cannot take advantage of a standardized request/response pattern or chained middlewares.

Developers conforming to PSR-7 and PSR-15 consequently need to re-implement custom middlewares for Yii2, which runs contrary to the `fast, secure, effecient` mantra of Yii2. This library helps to alleviate some of that pain.

## How this works

This package provides three classes within the `yii\Psr7\web` namespace, `Application`, `Request`, and `Response`, and a `Psr7ResponseTrait` trait that can be used to add PSR7 response capabilities to your existing classes that extend `yii\web\Response`.

To handle inbound requests, the `yii\Psr7\web\Application` component acts as a stand in replacement for `yii\web\Application` for use in your task runner. It's constructor takes the standard Yii2 configuration array, and a additional `ServerRequestInterface` instance. The `Application` component then instantiates a `yii\Psr7\web\Request` object using the `ServerRequestInterface` provided.

> Since `yii\web\Application::bootstrap` uses the `request` component, the request component needs to be properly constructed during the application constructor, as opposed to simply calling `$app->handleRequest($psr7Request);`

`yii\Psr7\web\Request` is a stand-in replacement for `yii\web\Request`. It's only purpose is to provide a interface between `ServerRequestInterface` and the standard `yii\web\Request` API.

Within your modules, controllers, actions, `Yii::$app->request` and `Yii::$app->response` may be used normally without any changes.

Before the application exists, it will call `getPsr7Response` on your `response` component. If you're using `yii\web\Response`, simply change your `response` component class in your application configuration to `yii\Psr7\web\Response`. If you're using a custom `Response` object, simply add the `yii\Psr7\web\traits\Psr7ResponseTrait` trait to your `Response` object that extends `yii\web\Response` to gain the necessary behaviors.

## Limitations

- File streams currently don't work (eg `yii\web\Response::sendFile`, `yii\web\Response::sendContentAsFile`, `yii\web\Response::sendStreamAsFile`)
- The Yii2 debug toolbar `yii2-debug` will show the wrong request time and memory usage.
- Yii2 can't send `SameSite` cookies

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
- [x] Implement `yii\web\Response::redirect`.
- [x] Implement `yii\web\Response::refresh`.
- [x] GET query parameters `yii\web\Request::get()`.
- [x] POST parameters `yii\web\Request::post()`.
- [x] `yii\web\Request::getAuthCredentials()`.
- [x] `yii\web\Request::loadCookies()`.
- [x] Per-action Middleware authentication handling.
- [x] Per-action middleware chains.
- [x] Reuse `Application` component instead of re-instantiating in each looåp.
- [x] `yii\web\ErrorHandler` implementation.
- [x] Run `yii-app-basic`.
- [x] Bootstrap with `yii\log\Target`.
- [x] session handling
- [x] `yii-debug`.
- [x] `yii-gii`.
- [x] Fix fatal memory leak under load
- [ ] `yii\filters\auth\CompositeAuth` compatability.
- [ ] Implement comparable `sendFile`.
- [ ] `yii\web\Request::$methodParam` support. (Not really applicable to `ServerRequestInterface`)
- [ ] Test Coverage

-----

This project is licensed under the BSD-3-Clause license. See [LICENSE](LICENSE) for more details.
