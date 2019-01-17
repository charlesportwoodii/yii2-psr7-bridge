<?php declare(strict_types=1);

namespace yii\Psr7\web;

use yii\Psr7\web\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use yii\base\Component;
use Yii;

use ReflectionMethod;

/**
 * A Yii2 compatible A PSR-15 RequestHandlerInterface Application component
 *
 * This class is a \yii\web\Application substitute for use with PSR-7 and PSR-15 middlewares
 */
class Application extends \yii\web\Application implements RequestHandlerInterface
{
    /**
     * @var array The configuration
     */
    private $config;

    private $isBootstrapped = false;
    /**
     * Overloaded constructor to persist configuration
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;

        // Set the environment aliases
        Yii::setAlias('@webroot', \getenv('YII_ALIAS_WEBROOT'));
        Yii::setAlias('@web', \getenv('YII_ALIAS_WEB'));
    }

    /**
     * Re-registers all components with the original configuration
     * @return void
     */
    protected function reset(ServerRequestInterface $request)
    {
        Yii::$app = $this;
        static::setInstance($this);
        $config = $this->config;
        $config['components']['request']['psr7Request'] = $request;

        $this->state = self::STATE_BEGIN;
        $this->preInit($config);
        $this->registerErrorHandler($config);
        Component::__construct($config);

        $this->bootstrap();
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $this->state = self::STATE_INIT;
    }

    /**
     * {@inheritdoc}
     */
    protected function bootstrap()
    {
        $method = new ReflectionMethod(get_parent_class(get_parent_class($this)), 'bootstrap');
        $method->setAccessible(true);
        $method->invoke($this);
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
            $this->reset($request);
            $this->state = self::STATE_BEFORE_REQUEST;
            $this->trigger(self::EVENT_BEFORE_REQUEST);

            $this->state = self::STATE_HANDLING_REQUEST;

            $response = $this->handleRequest($this->getRequest());

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
     * Terminates the application
     *
     * This method handles final log flushing and session termination
     *
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function terminate(ResponseInterface $response = null) : ResponseInterface
    {
        // Final flush of Yii2's logger to ensure log data is written at the end of the request
        // and to ensure Yii2 Debug populates correctly
        if (($logger = Yii::getLogger()) !== null) {
            $logger->flush(true);
        }

        // Close the session
        $this->getSession()->close();

        // De-register the event handlers for this class
        $this->off(self::EVENT_BEFORE_REQUEST);
        $this->off(self::EVENT_AFTER_REQUEST);

        // Detatch response events
        $r = $this->getResponse();
        $r->off(Response::EVENT_AFTER_PREPARE);
        $r->off(Response::EVENT_AFTER_SEND);
        $r->off(Response::EVENT_BEFORE_SEND);

        // Return the parent response
        return $response;
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
