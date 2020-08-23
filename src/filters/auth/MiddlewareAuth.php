<?php

declare(strict_types=1);

namespace yii\Psr7\filters\auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Yii;
use yii\filters\auth\AuthInterface;

use yii\filters\auth\AuthMethod;
use yii\web\HttpException;
use yii\web\IdentityInterface;
use yii\web\Request;
use yii\web\Response;
use yii\web\User;

class MiddlewareAuth extends AuthMethod implements AuthInterface, RequestHandlerInterface
{
    const TOKEN_ATTRIBUTE_NAME = 'yii_psr7_token_attr';

    /**
     * The internal HTTP status code to throw to indicate that the middleware
     * processing did yet response, and that further handling is required.
     *
     * @var integer
     */
    private $continueStatusCode = 109;

    /**
     * The PSR-15 middleware to run
     *
     * @var MiddlewareInterface $middleware
     */
    public $middleware;

    /**
     * The attribute to use for loginByAccessToken
     *
     * @var string
     */
    public $attribute;

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
     * Authenticates a user
     *
     * @param  User     $user
     * @param  request  $request
     * @param  Response $response
     * @return IdentityInterface|null
     */
    public function authenticate($user, $request, $response)
    {
        if ($this->attribute === null) {
            Yii::error('Token attribute not set.', 'yii\Psr7\filters\auth\MiddlewareAuth');
            $response->setStatusCode(500);
            $response->content = 'An unexpected error occurred.';
            $this->handleFailure($response);
        }

        // Process the PSR-15 middleware
        $instance = $this;
        $process = $this->middleware->process(Yii::$app->request->getPsr7Request(), $instance);

        // Update the PSR-7 Request object
        Yii::$app->request->setPsr7Request(
            $instance->getModifiedRequest()
        );

        // If we get a continue status code and the expected user attribute is set
        // attempt to log this user in use yii\web\User::loginByAccessToken
        if ($process->getStatusCode() === $this->continueStatusCode
            && $process->hasHeader(static::TOKEN_ATTRIBUTE_NAME)
        ) {
            if ($identity = $user->loginByAccessToken(
                $process->getHeader(static::TOKEN_ATTRIBUTE_NAME),
                \get_class($this)
            )
            ) {
                return $identity;
            }
        }

        // Populate the response object
        $response->withPsr7Response($process);
        unset($process);
        return null;
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
        return new \Laminas\Diactoros\Response\EmptyResponse(
            $this->continueStatusCode,
            [
                static::TOKEN_ATTRIBUTE_NAME => $request->getAttribute($this->attribute)
            ]
        );
    }

    /**
     * If the authentication event failed, rethrow as an HttpException to end processing
     *
     * @param  Response $response
     * @throws HttpException
     * @return void
     */
    public function handleFailure($response)
    {
        throw new HttpException(
            $response->getStatusCode(),
            $response->content
        );
    }
}
