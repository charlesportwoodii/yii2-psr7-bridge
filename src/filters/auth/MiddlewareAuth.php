<?php declare(strict_types=1);

namespace yii\Psr7\filters\auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;

use yii\base\Action;
use yii\filters\auth\AuthMethod;
use yii\filters\auth\AuthInterface;

use yii\web\Request;
use yii\web\Response;
use yii\web\HttpException;
use yii\web\User;
use yii\web\IdentityInterface;
use Yii;

class MiddlewareAuth extends AuthMethod implements AuthInterface, RequestHandlerInterface
{
    const TOKEN_ATTRIBUTE_NAME = 'yii_psr7_token_attr';

    /**
     * The internal HTTP status code to throw to indicate that the middleware
     * processing did yet response, and that further handling is required.
     *
     * @var integer
     */
    private $continueStatusCode = 103;

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
     * Authenticates a user
     *
     * @param User $user
     * @param request $request
     * @param Response $response
     * @return IdentityInterface|null
     */
    public function authenticate($user, $request, $response)
    {
        if ($this->attribute === null) {
            Yii::error("Token attribute not set.", 'yii\Psr7\filters\auth\MiddlewareAuth');
            $response->setStatusCode(500);
            $response->content = 'An unexpected error occurred.';
            $this->handleFailure($response);
        }

        // Process the PSR-15 middleware
        $process = $this->middleware->process($request->getPsr7Request(), $this);

        // If we get a continue status code and the expected user attribute is set
        // attempt to log this user in use yii\web\User::loginByAccessToken
        if (
            $process->getStatusCode() === $this->continueStatusCode &&
            $process->hasHeader(static::TOKEN_ATTRIBUTE_NAME)
        ) {
            if ($identity = $user->loginByAccessToken(
                $process->getHeader(static::TOKEN_ATTRIBUTE_NAME),
                \get_class($this)
            )) {
                return $identity;
            }
        }

        // Populate the response object
        $response->withPsr7Response($process);
        return null;
    }

    /**
     * RequestHandlerInterface mock method to short-circuit PSR-15 middleware processing
     * If this method is called, then it indicates that the existing requests have not yet
     * returned a response, and that PSR-15 middleware processing has ended for this filter.
     *
     * An out-of-spec HTTP status code is thrown to not interfere with existing HTTP specifications.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
       return new \Zend\Diactoros\Response\EmptyResponse(
           $this->continueStatusCode,
           [
               static::TOKEN_ATTRIBUTE_NAME => $request->getAttribute($this->attribute)
           ]
        );
    }

    /**
     * If the authentication event failed, rethrow as an HttpException to end processing
     *
     * @param Response $response
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