<?php declare(strict_types=1);

namespace yii\Psr7\web;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class Application extends \yii\web\Application
{
    /**
     * Overloaded constructor
     *
     * @param array $config
     * @param ServerRequestInterface $request
     */
    public function __construct(array $config = [], ServerRequestInterface $request = null)
    {
        if ($request === null) {
            throw new Exception('`Request` must be an instance of `ServerRequestInterface`');
        }

        $config['components']['request']['request'] = $request;

        parent::__construct($config);
    }

    /**
     * Handles a PSR7 Request and returns a PSR7 response
     *
     * @return ResponseInterface
     */
    public function handlePsr7Request() : ResponseInterface
    {
        try {
            $response = $this->handleRequest($this->getRequest());
            return $response->getPsr7Response();
        } catch (\Exception $e) {
            $response = $this->getErrorHandler()->handleException($e);
            return $response->getPsr7Response();
        } catch (\Throwable $e) {
            $response = $this->getErrorHandler()->handleException($e);
            return $response->getPsr7Response();
        }
    }

        /**
     * {@inheritdoc}
     */
    public function coreComponents()
    {
        return array_merge(parent::coreComponents(), [
            'errorHandler' => ['class' => \yii\Psr7\web\ErrorHandler::class],
        ]);
    }
}
