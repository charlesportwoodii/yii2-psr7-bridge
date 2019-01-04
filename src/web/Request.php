<?php

namespace yii\Psr7\web;

use Psr\Http\Message\ServerRequestInterface;

use Yii;

use yii\base\InvalidConfigException;
use yii\validators\IpValidator;
use yii\web\HeaderCollection;
use yii\web\NotFoundHttpException;

class Request extends \yii\web\Request
{
    /**
     * The PSR7 interface
     *
     * @var ServerRequestInterface
     */
    public $request;

    /**
     * @inheritdoc
     */
    private $_rawBody;

    /**
     * @inheritdoc
     */
    private $_headers;

    /**
     * @inheritdoc
     */
    private $_bodyParams;

    /**
     * @inheritdoc
     */
    private $_queryParams;

    /**
     * @inheritdoc
     */
    private $_hostInfo;

    /**
     * @inheritdoc
     */
    private $_hostName;

    /**
     * @inheritdoc
     */
    private $_scriptFile;

    /**
     * @inheritdoc
     */
    private $_scriptUrl;

    /**
     * @inheritdoc
     */
    public function resolve()
    {
        $result = Yii::$app->getUrlManager()->parseRequest($this);
        if ($result !== false) {
            list($route, $params) = $result;
            return [$route, $this->getQueryParams()];
        }

        throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
    }

    /**
     * @inheritdoc
     */
    public function getHeaders()
    {
        if ($this->_headers === null) {
            $this->_headers = new HeaderCollection;
            $headers = $this->request->getHeaders();
            foreach ($headers as $name => $value) {
                $this->_headers->add($name, $value);
            }
            $this->filterHeaders($this->_headers);
        }

        return $this->_headers;
    }

    /**
     * @inheritdoc
     */
    public function getMethod()
    {
        return $this->request->getMethod();
    }

    /**
     * @inheritdoc
     */
    public function getRawBody()
    {
        if ($this->_rawBody === null) {
            $request = clone $this->request;
            $body = $request->getBody();
            $body->rewind();
            $this->setRawBody((string)$body->getContents());
        }

        return $this->_rawBody;
    }

    public function setRawBody($body)
    {
        $this->_rawBody = $body;
    }

    /**
     * @inheritdoc
     */
    public function getQueryParams()
    {
        return $this->request->getQueryParams();
    }

    /**
     * @inheritdoc
     */
    public function getScriptUrl()
    {
        if ($this->_scriptUrl === null) {
            $scriptFile = $this->getScriptFile();
            $scriptName = basename($scriptFile);
            if (isset($this->getServerParams()['SCRIPT_NAME']) && basename($this->getServerParams()['SCRIPT_NAME']) === $scriptName) {
                $this->_scriptUrl = $this->getServerParams()['SCRIPT_NAME'];
            } elseif (isset($this->getServerParams()['PHP_SELF']) && basename($this->getServerParams()['PHP_SELF']) === $scriptName) {
                $this->_scriptUrl = $this->getServerParams()['PHP_SELF'];
            } elseif (isset($this->getServerParams()['ORIG_SCRIPT_NAME']) && basename($this->getServerParams()['ORIG_SCRIPT_NAME']) === $scriptName) {
                $this->_scriptUrl = $this->getServerParams()['ORIG_SCRIPT_NAME'];
            } elseif (isset($this->getServerParams()['PHP_SELF']) && ($pos = strpos($this->getServerParams()['PHP_SELF'], '/' . $scriptName)) !== false) {
                $this->_scriptUrl = substr($this->getServerParams()['SCRIPT_NAME'], 0, $pos) . '/' . $scriptName;
            } elseif (!empty($this->getServerParams()['DOCUMENT_ROOT']) && strpos($scriptFile, $this->getServerParams()['DOCUMENT_ROOT']) === 0) {
                $this->_scriptUrl = str_replace([$this->getServerParams()['DOCUMENT_ROOT'], '\\'], ['', '/'], $scriptFile);
            } else {
                throw new InvalidConfigException('Unable to determine the entry script URL.');
            }
        }

        return $this->_scriptUrl;
    }

    /**
     * @inheritdoc
     */
    public function getScriptFile()
    {
        if (isset($this->_scriptFile)) {
            return $this->_scriptFile;
        }

        if (isset($this->getServerParams()['SCRIPT_FILENAME'])) {
            return $this->getServerParams()['SCRIPT_FILENAME'];
        }

        throw new InvalidConfigException('Unable to determine the entry script file path.');
    }

    /**
     * @inheritdoc
     */
    protected function resolvePathInfo()
    {
        $pathInfo = $this->getUrl();
        if (($pos = strpos($pathInfo, '?')) !== false) {
            $pathInfo = substr($pathInfo, 0, $pos);
        }
        $pathInfo = urldecode($pathInfo);
        // try to encode in UTF8 if not so
        // http://w3.org/International/questions/qa-forms-utf-8.html
        if (!preg_match('%^(?:
            [\x09\x0A\x0D\x20-\x7E]              # ASCII
            | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
            | \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
            | \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
            | \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
            | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
            | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
            )*$%xs', $pathInfo)
        ) {
            $pathInfo = utf8_encode($pathInfo);
        }

        $scriptUrl = $this->getScriptUrl();
        $baseUrl = $this->getBaseUrl();

        if (strpos($pathInfo, $scriptUrl) === 0) {
            $pathInfo = substr($pathInfo, strlen($scriptUrl));
        } elseif ($baseUrl === '' || strpos($pathInfo, $baseUrl) === 0) {
            $pathInfo = substr($pathInfo, strlen($baseUrl));
        } elseif (isset($this->getServerParams()['PHP_SELF']) && strpos($this->getServerParams()['PHP_SELF'], $scriptUrl) === 0) {
            $pathInfo = substr($this->getServerParams()['PHP_SELF'], strlen($scriptUrl));
        } else {
            throw new InvalidConfigException('Unable to determine the path info of the current request.');
        }

        if (strncmp($pathInfo, '/', 1) === 0) {
            $pathInfo = substr($pathInfo, 1);
        }

        return (string) $pathInfo;
    }

    /**
     * @inheritdoc
     */
    protected function resolveRequestUri()
    {
        $this->request->getUri()->getPath();
    }

    /**
     * @inheritdoc
     */
    public function getQueryString()
    {
        return $this->request->getQuery();
    }

    /**
     * @inheritdoc
     */
    public function getServerParams()
    {
        return $this->request->getServerParams();
    }

    /**
     * @inheritdoc
     */
    public function getIsSecureConnection()
    {
        if ($this->request->getUri()->getScheme() === 'https') {
            return true;
        }

        foreach ($this->secureProtocolHeaders as $header => $values) {
            if (($headerValue = $this->headers->get($header, null)) !== null) {
                foreach ($values as $value) {
                    if (strcasecmp($headerValue, $value) === 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getServerName()
    {
        return $this->request->getUri()->getHost();
    }

    /**
     * @inheritdoc
     */
    public function getServerPort()
    {
        return $this->request->getUri()->getPort();
    }

    /**
     * @inheritdoc
     */
    public function getAuthCredentials() {}

    /**
     * @inheritdoc
     */
    protected function loadCookies() {}
}
