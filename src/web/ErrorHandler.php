<?php declare(strict_types=1);

namespace yii\Psr7\web;

use Yii;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\UserException;
use yii\helpers\VarDumper;
use yii\Psr7\web\Response;

class ErrorHandler extends \yii\web\ErrorHandler
{
    /**
     * @inheritdoc
     */
    public function handleException($exception)
    {
        if ($exception instanceof ExitException) {
            return;
        }

        $this->exception = $exception;

        // disable error capturing to avoid recursive errors while handling exceptions
        $this->unregister();

        // set preventive HTTP status code to 500 in case error handling somehow fails and headers are sent
        // HTTP exceptions will override this value in renderException()
        if (PHP_SAPI !== 'cli') {
            http_response_code(500);
        }

        try {
            $this->logException($exception);
            if ($this->discardExistingOutput) {
                $this->clearOutput();
            }
            $response = $this->renderException($exception);
            if (!YII_ENV_TEST) {
                \Yii::getLogger()->flush(true);
                return $response;
            }
        } catch (\Exception $e) {
            // an other exception could be thrown while displaying the exception
            return $this->handleFallbackExceptionMessage($e, $exception);
        } catch (\Throwable $e) {
            // additional check for \Throwable introduced in PHP 7
            return $this->handleFallbackExceptionMessage($e, $exception);
        }

        $this->exception = null;
    }

    /**
     * @inheritdoc
     */
    protected function handleFallbackExceptionMessage($exception, $previousException)
    {
        $response = new Response;
        $msg = "An Error occurred while handling another error:\n";
        $msg .= (string) $exception;
        $msg .= "\nPrevious exception:\n";
        $msg .= (string) $previousException;
        $response->data = $msg;
        if (YII_DEBUG) {
            if (PHP_SAPI === 'cli') {
                $response->data = $msg;
            } else {
                $response->data = '<pre>' . htmlspecialchars($msg, ENT_QUOTES, Yii::$app->charset) . '</pre>';
            }
        } else {
            $response->data = 'An internal server error occurred.';
        }
        $response->data .= "\n\$_SERVER = " . VarDumper::export($_SERVER);
        error_log($response->data);

        return $response;
    }

    /**
     * @inheritdoc
     */
    protected function renderException($exception)
    {
        if (Yii::$app->has('response')) {
            $response = Yii::$app->getResponse();
            // reset parameters of response to avoid interference with partially created response data
            // in case the error occurred while sending the response.
            $response->isSent = false;
            $response->stream = null;
            $response->data = null;
            $response->content = null;
        } else {
            $response = new Response;
        }

        $response->setStatusCodeByException($exception);

        $useErrorView = $response->format === Response::FORMAT_HTML && (!YII_DEBUG || $exception instanceof UserException);

        if ($useErrorView && $this->errorAction !== null) {
            $result = Yii::$app->runAction($this->errorAction);
            if ($result instanceof Response) {
                $response = $result;
            } else {
                $response->data = $result;
            }
        } elseif ($response->format === Response::FORMAT_HTML) {
            if ($this->shouldRenderSimpleHtml()) {
                // AJAX request
                $response->data = '<pre>' . $this->htmlEncode(static::convertExceptionToString($exception)) . '</pre>';
            } else {
                // if there is an error during error rendering it's useful to
                // display PHP error in debug mode instead of a blank screen
                if (YII_DEBUG) {
                    ini_set('display_errors', 'true');
                }
                $file = $useErrorView ? $this->errorView : $this->exceptionView;
                $response->data = $this->renderFile(
                    $file,
                    [
                        'exception' => $exception,
                    ]
                );
            }
        } elseif ($response->format === Response::FORMAT_RAW) {
            $response->data = static::convertExceptionToString($exception);
        } else {
            $response->data = $this->convertExceptionToArray($exception);
        }

        return $response;
    }
}
