<?php declare(strict_types=1);

namespace yii\Psr7\web;

use yii\Psr7\web\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use yii\base\Component;
use Yii;

/**
 * A Yii2 compatible A PSR-15 RequestHandlerInterface Application component
 *
 * This class is a \yii\web\Application substitute for use with PSR-7 and PSR-15 middlewares
 */
class Application extends \yii\base\Application implements RequestHandlerInterface
{
    /**
     * @var string the default route of this application. Defaults to 'site'.
     */
    public $defaultRoute = 'site';

    /**
     * @var array the configuration specifying a controller action which should handle
     * all user requests. This is mainly used when the application is in maintenance mode
     * and needs to handle all incoming requests via a single action.
     * The configuration is an array whose first element specifies the route of the action.
     * The rest of the array elements (key-value pairs) specify the parameters to be bound
     * to the action. For example,
     *
     * ```php
     * [
     *     'offline/notice',
     *     'param1' => 'value1',
     *     'param2' => 'value2',
     * ]
     * ```
     *
     * Defaults to null, meaning catch-all is not used.
     */
    public $catchAll;

    /**
     * @var Controller the currently active controller instance
     */
    public $controller;

    /**
     * @var array The configuration
     */
    private $config;

    /**
     * Overloaded constructor to persist configuration
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        parent::__construct($config);
    }

    /**
     * Re-registers all components with the original configuration
     * @return void
     */
    public function reset()
    {
        Yii::$app = $this;
        static::setInstance($this);
        $config = $this->config;

        $this->state = self::STATE_BEGIN;
        $this->preInit($config);
        $this->registerErrorHandler($config);
        Component::__construct($config);
    }

    /**
     * {@inheritdoc}
     */
    protected function bootstrap()
    {
        $request = $this->getRequest();
        Yii::setAlias('@webroot', \getenv('YII_ALIAS_WEBROOT'));
        Yii::setAlias('@web', $request->getBaseUrl());
        parent::bootstrap();
    }

    /**
     * PSR-15 RequestHandlerInterface
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        try {
            $this->state = self::STATE_BEFORE_REQUEST;
            $this->trigger(self::EVENT_BEFORE_REQUEST);

            $this->state = self::STATE_HANDLING_REQUEST;
            $yiiRequest = $this->getRequest();
            $yiiRequest->setPsr7Request($request);

            $response = $this->handleRequest($yiiRequest);

            $this->state = self::STATE_AFTER_REQUEST;
            $this->trigger(self::EVENT_AFTER_REQUEST);

            $this->state = self::STATE_END;
            return $response->getPsr7Response();
        } catch (\Exception $e) {
            return $this->handleError($e);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Handles exceptions and errors thrown by the request handler
     *
     * @param \Throwable|\Exception $exception
     * @return ResponseInterface
     */
    private function handleError(\Throwable $exception) : ResponseInterface
    {
        $response = $this->getErrorHandler()->handleException($exception);

        $this->trigger(self::EVENT_AFTER_REQUEST);
        $this->state = self::STATE_END;

        return $response->getPsr7Response();
    }

    /**
     * Handles the specified request.
     * @param Request $request the request to be handled
     * @return Response the resulting response
     *
     * @throws NotFoundHttpException if the requested route is invalid
     */
    public function handleRequest($request)
    {
        if (empty($this->catchAll)) {
            try {
                list($route, $params) = $request->resolve();
            } catch (UrlNormalizerRedirectException $e) {
                $url = $e->url;
                if (is_array($url)) {
                    if (isset($url[0])) {
                        // ensure the route is absolute
                        $url[0] = '/' . ltrim($url[0], '/');
                    }
                    $url += $request->getQueryParams();
                }
                return $this->getResponse()->redirect(Url::to($url, $e->scheme), $e->statusCode);
            }
        } else {
            $route = $this->catchAll[0];
            $params = $this->catchAll;
            unset($params[0]);
        }

        try {
            Yii::debug("Route requested: '$route'", __METHOD__);
            $this->requestedRoute = $route;
            $result = $this->runAction($route, $params);

            if ($result instanceof Response) {
                return $result;
            }

            $response = $this->getResponse();

            if ($result !== null) {
                $response->data = $result;
            }

            return $response;
        } catch (InvalidRouteException $e) {
            throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'), $e->getCode(), $e);
        }
    }

    private $_homeUrl;

    /**
     * @return string the homepage URL
     */
    public function getHomeUrl()
    {
        if ($this->_homeUrl === null) {
            if ($this->getUrlManager()->showScriptName) {
                return $this->getRequest()->getScriptUrl();
            }
            return $this->getRequest()->getBaseUrl() . '/';
        }
        return $this->_homeUrl;
    }

    /**
     * @param string $value the homepage URL
     */
    public function setHomeUrl($value)
    {
        $this->_homeUrl = $value;
    }

    /**
     * Returns the error handler component.
     * @return ErrorHandler the error handler application component.
     */
    public function getErrorHandler()
    {
        return $this->get('errorHandler');
    }

    /**
     * Returns the request component.
     * @return Request the request component.
     */
    public function getRequest()
    {
        return $this->get('request');
    }

    /**
     * Returns the response component.
     * @return Response the response component.
     */
    public function getResponse()
    {
        return $this->get('response');
    }

    /**
     * Returns the session component.
     * @return Session the session component.
     */
    public function getSession()
    {
        return $this->get('session');
    }

    /**
     * Returns the user component.
     * @return User the user component.
     */
    public function getUser()
    {
        return $this->get('user');
    }

    /**
     * {@inheritdoc}
     */
    public function coreComponents()
    {
        return array_merge(parent::coreComponents(), [
            'request' => ['class' => \yii\Psr7\web\Request::class],
            'response' => ['class' => \yii\Psr7\web\Response::class],
            'session' => ['class' => \yii\web\Session::class],
            'user' => ['class' => \yii\web\User::class],
            'errorHandler' => ['class' => \yii\Psr7\web\ErrorHandler::class],
        ]);
    }
}
