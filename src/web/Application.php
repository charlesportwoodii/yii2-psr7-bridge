<?php declare(strict_types=1);

namespace yii\Psr7\web;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;

use Yii;
use yii\base\Component;

use yii\Psr7\web\monitor\ConnectionMonitor;
use yii\Psr7\web\monitor\EventMonitor;

use yii\Psr7\web\Response;

/**
 * A Yii2 compatible A PSR-15 RequestHandlerInterface Application component
 *
 * This class is a \yii\web\Application substitute for use with PSR-7 and PSR-15 middlewares
 */
class Application extends \yii\web\Application implements RequestHandlerInterface
{
    /**
     * @inheritdoc
     */
    public $version = "0.0.2";

    /**
     * @var array The configuration
     */
    private $config;

    /**
     * @var int $memoryLimit
     */
    private $memoryLimit;

    /**
     * @var array $monitors
     */
    private $monitors = [];

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

        // This is necessary to get \yii\web\Session to work properly.
        ini_set('use_cookies', 'false');
        ini_set('use_only_cookies', 'true');

        Yii::$app = $this;
        static::setInstance($this);
        $this->monitors = $this->monitors();
    }

    /**
     * Sets up any monitors we want
     *
     * @return array
     */
    public function monitors()
    {
        return [
            new ConnectionMonitor,
            new EventMonitor
        ];
    }

    /**
     * Re-registers all components with the original configuration
     *
     * @return void
     */
    protected function reset(ServerRequestInterface $request)
    {
        // Override YII_BEGIN_TIME if possible for yii2-debug
        // and other modules that depend on it
        if (\function_exists('uopz_redefine')) {
            \uopz_redefine('YII_BEGIN_TIME', microtime(true));
        }

        foreach ($this->monitors as $monitor) {
            $monitor->on();
        }

        $config = $this->config;

        $config['components']['request']['psr7Request'] = $request;

        $this->state = self::STATE_BEGIN;
        $this->preInit($config);
        $this->registerErrorHandler($config);
        Component::__construct($config);

        // Session data has to be explicitly loaded before any bootstrapping occurs to ensure compatability
        // with bootstrapped components (such as yii2-debug).
        if (($session = $this->getSession()) !== null) {
            // Close the session if it was open.
            $session->close();

            // If a session cookie is defined, load it into Yii::$app->session
            if (isset($request->getCookieParams()[$session->getName()])) {
                $session->setId($request->getCookieParams()[$session->getName()]);
            }
        }

        // Open the session before any modules that need it are bootstrapped.
        $this->ensureBehaviors();
        $session->open();
        $this->bootstrap();

        // Once bootstrapping is done we can close the session.
        // Accessing it in the future will re-open it.
        $session->close();
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
        // Call the bootstrap method in \yii\base\Application instead of \yii\web\Application
        $method = new ReflectionMethod(get_parent_class(get_parent_class($this)), 'bootstrap');
        $method->setAccessible(true);
        $method->invoke($this);
    }

    /**
     * PSR-15 RequestHandlerInterface
     *
     * @param  ServerRequestInterface $request
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
            return $this->terminate($response->getPsr7Response());
        } catch (\Exception $e) {
            return $this->terminate($this->handleError($e));
        } catch (\Throwable $e) {
            return $this->terminate($this->handleError($e));
        }
    }

    /**
     * Terminates the application
     *
     * This method handles final log flushing and session termination
     *
     * @param  ResponseInterface $response
     * @return ResponseInterface
     */
    protected function terminate(ResponseInterface $response) : ResponseInterface
    {
        // Handle any monitors that are attached
        foreach ($this->monitors as $monitor) {
            $monitor->shutdown();
        }

        // Reset fileuploads
        \yii\web\UploadedFile::reset();

        // Reset the logger
        if (($logger = Yii::getLogger()) !== null) {
            $logger->flush(true);
        }

        // Return the parent response
        return $response;
    }

    /**
     * Handles exceptions and errors thrown by the request handler
     *
     * @param  \Throwable|\Exception $exception
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
        return array_merge(
            parent::coreComponents(),
            [
                'request' => ['class' => \yii\Psr7\web\Request::class],
                'response' => ['class' => \yii\Psr7\web\Response::class],
                'session' => ['class' => \yii\web\Session::class],
                'user' => ['class' => \yii\web\User::class],
                'errorHandler' => ['class' => \yii\Psr7\web\ErrorHandler::class],
            ]
        );
    }

    /**
     * Cleanup function to be called at the end of the script execution
     * This will automatically run garbage collection, and if the script
     * is within 5% of the memory limit will pre-maturely kill the worker
     * forcing your task-runner to rebuild it.
     *
     * This is implemented to avoid requests failing due to memory exhaustion
     *
     * @return boolean
     */
    public function clean()
    {
        gc_collect_cycles();
        $limit = $this->getMemoryLimit();
        $bound = $limit * .90;
        $usage = memory_get_usage(true);
        if ($usage >= $bound) {
            return true;
        }

        return false;
    }

    /**
     * Retrieves the current memory as integer bytes
     *
     * @return int
     */
    private function getMemoryLimit() : int
    {
        if (!$this->memoryLimit) {
            $limit  = ini_get('memory_limit');
            sscanf($limit, '%u%c', $number, $suffix);
            if (isset($suffix)) {
                $number = $number * pow(1024, strpos(' KMG', strtoupper($suffix)));
            }

            $this->memoryLimit = $number;
        }

        return (int)$this->memoryLimit;
    }
}
