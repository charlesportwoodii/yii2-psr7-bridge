<?php declare(strict_types=1);

namespace yii\Psr7\web\traits;

use Yii;
use Psr\Http\Message\ResponseInterface;

trait Psr7ResponseTrait
{
     /**
     * Returns a PSR7 response
     *
     * @return ResponseInterface
     */
    public function getPsr7Response() : ResponseInterface
    {
        $this->trigger(self::EVENT_BEFORE_SEND);
        $this->prepare();
        $this->trigger(self::EVENT_AFTER_PREPARE);
        $stream = $this->getPsr7Content();

        Yii::info($this->getCookies()->toArray());
        $response = new \Zend\Diactoros\Response(
            $stream,
            $this->getStatusCode(),
            $this->getPsr7Headers()
        );

        $this->trigger(self::EVENT_AFTER_SEND);
        $this->isSent = true;

        return $response;
    }

    /**
     * Returns all headers to be sent to the client
     *
     * @return array
     */
    private function getPsr7Headers() : array
    {
        $headers = [];
        foreach ($this->getHeaders() as $name => $values) {
            $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
            // set replace for first occurrence of header but false afterwards to allow multiple
            $replace = true;
            foreach ($values as $value) {
                if ($replace) {
                    $headers[$name] = $value;
                }
                $replace = false;
            }
        }

        return \array_merge($headers, $this->getPsr7Cookies());
    }

    private function getPsr7Cookies()
    {
        $cookies = [];
        $request = Yii::$app->getRequest();
        if ($request->enableCookieValidation) {
            if ($request->cookieValidationKey == '') {
                throw new InvalidConfigException(get_class($request) . '::cookieValidationKey must be configured with a secret key.');
            }
            $validationKey = $request->cookieValidationKey;
        }
        foreach ($this->getCookies() as $cookie) {
            $value = $cookie->value;
            if ($cookie->expire != 1 && isset($validationKey)) {
                $value = Yii::$app->getSecurity()->hashData(serialize([$cookie->name, $value]), $validationKey);
            }

            $data = "$cookie->name=$value";

            Yii::info(print_r($cookie, true));
            if ($cookie->expire != null) {
                $data .= "; Expires={$cookie->expire}";
            }

            if ($cookie->path != null) {
                $data .= "; Path={$cookie->path}";
            }

            if ($cookie->domain != null) {
                $data .= "; Domain={$cookie->domain}";
            }

            if ($cookie->secure != null) {
                $data .= "; Secure";
            }

            if ($cookie->httpOnly !== null) {
                $data .= "; HttpOnly";
            }

            $cookies['Set-Cookie'][] = $data;
        }

        return $cookies;
    }

    /**
     * Returns the PSR7 Stream
     *
     * @return stream
     */
    private function getPsr7Content()
    {
        if ($this->stream === null) {
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, $this->content);
            rewind($stream);
            $this->stream = $stream;
        }

        return $this->stream;
    }
}
