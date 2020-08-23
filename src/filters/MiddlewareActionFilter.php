<?php

declare(strict_types=1);

namespace yii\Psr7\filters;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Yii;
use yii\base\ActionFilter;

class MiddlewareActionFilter extends ActionFilter implements RequestHandlerInterface
{
    /**
     * The internal HTTP status code to throw to indicate that the middleware
     * processing did yet response, and that further handling is required.
     *
     * @var integer $continueStatusCode
     */
    private $continueStatusCode = 109;

    /**
     * PSR-15 middlewares to execute before the action runs
     *
     * @var MiddlewareInterface[] $middlewares
     */
    public $middlewares = [];

    /**
     * The modified request interface
     *
     * @var ServerRequestInterface $request
     */
    private $request;

    /**
     * Returns the modified request
     *
     * @return ServerRequestInterface
     */
    protected function getModifiedRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Before action
     *
     * @param  \yii\base\Action $action
     * @return void
     */
    public function beforeAction($action)
    {
        // If there are no middlewares attached, skip this behavior
        if (!isset($this->middlewares) || empty($this->middlewares)) {
            return true;
        }

        $instance = $this;

        foreach ($this->middlewares as $middleware) {
            $psr7Request = Yii::$app->request->getPsr7Request();
            if ($middleware instanceof \Closure) {
                $response = $middleware($psr7Request, $instance);
            } else {
                $response = $middleware->process($psr7Request, $instance);
            }

            // Update the request instance
            Yii::$app->request->setPsr7Request(
                $instance->getModifiedRequest()
            );

            // Populate the response
            Yii::$app->response->withPsr7Response($response);

            // If we got an out-of-spec HTTP code back, kill the status code since we shouldn't send it.
            if ($response->getStatusCode() === $this->continueStatusCode) {
                Yii::$app->response->setStatusCode(null);
            }

            // If we didn't get a continue response, stop processing and return false
            if ($response->getStatusCode() !== $this->continueStatusCode) {
                return false;
            }
        }

        return true;
    }

    /**
     * RequestHandlerInterface mock method to short-circuit PSR-15 middleware processing
     * If this method is called, then it indicates that the existing requests have not yet
     * returned a response, and that PSR-15 middleware processing has ended for this filter.
     *
     * An out-of-spec HTTP status code is thrown to not interfere with existing HTTP specifications.
     *
     * @param  ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;
        return new \Laminas\Diactoros\Response\EmptyResponse(
            $this->continueStatusCode
        );
    }
}
