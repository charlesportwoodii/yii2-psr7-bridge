<?php declare(strict_types=1);

namespace yii\Psr7\web;

use Psr\Http\Message\ServerRequestInterface;

use Yii;

use yii\base\InvalidConfigException;
use yii\validators\IpValidator;
use yii\web\HeaderCollection;
use yii\web\NotFoundHttpException;
use yii\web\RequestParserInterface;

class Request extends \yii\web\Request
{
    /**
     * The PSR7 interface
     * @var ServerRequestInterface
     */
    private $_psr7;

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
     * Sets the PSR-7 Request object
     *
     * @param ServerRequestInterface $request
     * @return void
     */
    public function setPsr7Request(ServerRequestInterface $request)
    {
        $this->_psr7 = $request;
    }

    /**
     * Returns the PSR7 request
     *
     * @return ServerRequestInterface|null
     */
    public function getPsr7Request() :? ServerRequestInterface
    {
        return $this->_psr7;
    }

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
            foreach ($this->_psr7->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    $this->_headers->add($name, $value);
                }
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
        return $this->_psr7->getMethod();
    }

    /**
     * @inheritdoc
     */
    public function getRawBody()
    {
        if ($this->_rawBody === null) {
            $request = clone $this->_psr7;
            $body = $request->getBody();
            $body->rewind();
            $this->setRawBody((string)$body->getContents());
        }

        return $this->_rawBody;
    }

    /**
     * @inheritdoc
     */
    public function setRawBody($body)
    {
        $this->_rawBody = $body;
    }

    /**
     * @inheritdoc
     */
    public function getBodyParams()
    {
        if ($this->_bodyParams === null) {
            // @todo: Implement method tunneling
            $rawContentType = $this->getContentType();
            if (($pos = strpos($rawContentType, ';')) !== false) {
                // e.g. text/html; charset=UTF-8
                $contentType = substr($rawContentType, 0, $pos);
            } else {
                $contentType = $rawContentType;
            }

            if (isset($this->parsers[$contentType])) {
                $parser = Yii::createObject($this->parsers[$contentType]);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException("The '$contentType' request parser is invalid. It must implement the yii\\web\\RequestParserInterface.");
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $rawContentType);
            } elseif (isset($this->parsers['*'])) {
                $parser = Yii::createObject($this->parsers['*']);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException('The fallback request parser is invalid. It must implement the yii\\web\\RequestParserInterface.');
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $rawContentType);
            } elseif ($this->getMethod() === 'POST') {
                // PHP has already parsed the body so we have all params in $_POST
                $this->_bodyParams = $this->_psr7->getParsedBody();
            } else {
                $this->_bodyParams = [];
                mb_parse_str($this->getRawBody(), $this->_bodyParams);
            }
        }

        return $this->_bodyParams;
    }

    /**
     * @inheritdoc
     */
    public function getQueryParams()
    {
        return $this->_psr7->getQueryParams();
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

        if (strncmp($pathInfo, '/', 1) === 0) {
            $pathInfo = substr($pathInfo, 1);
        }

        return (string)$pathInfo;
    }

    /**
     * @inheritdoc
     */
    protected function resolveRequestUri()
    {
        $uri = $this->_psr7->getUri();
        $requestUri =  $uri->getPath();
        $queryString = $this->getQueryString();
        if ($queryString !== '') {
            $requestUri .= '?' . $this->getQueryString();
        }

        if ($uri->getFragment() !== '') {
            $requestUri .= '#' . $uri->getFragment();
        }

        return $requestUri;
    }

    /**
     * @inheritdoc
     */
    public function getQueryString()
    {
        return $this->_psr7->getUri()->getQuery();
    }

    /**
     * @inheritdoc
     */
    public function getServerParams()
    {
        return $this->_psr7->getServerParams();
    }

    /**
     * @inheritdoc
     */
    public function getIsSecureConnection()
    {
        if ($this->_psr7->getUri()->getScheme() === 'https') {
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
        return $this->_psr7->getUri()->getHost();
    }

    /**
     * @inheritdoc
     */
    public function getServerPort()
    {
        return $this->_psr7->getUri()->getPort();
    }

    /**
     * @inheritdoc
     */
    public function getAuthCredentials()
    {
        // Go net/http transforms the UserInfo URL component to a Authorization: Basic header
        // If this header is present, automatically decode it and treat it as the UserInfo component
        $headers = $this->getHeaders();
        if ($headers->has('authorization')) {
            $authHeader = $headers->get('authorization');
            if (\substr($authHeader, 0, 6) === 'Basic ') {
                $credentials = \base64_decode(\str_replace('Basic ', '', $authHeader));
                return \explode(':', $credentials);
            }
        }

        return [null, null];
    }

    /**
     * @inheritdoc
     */
    protected function loadCookies()
    {
        $cookies = [];
        if ($this->enableCookieValidation) {
            if ($this->cookieValidationKey == '') {
                throw new InvalidConfigException(get_class($this) . '::cookieValidationKey must be configured with a secret key.');
            }

            foreach ($this->_psr7->getCookieParam() as $name => $value) {
                if (!is_string($value)) {
                    continue;
                }

                $data = Yii::$app->getSecurity()->validateData($value, $this->cookieValidationKey);
                if ($data === false) {
                    continue;
                }

                $data = @unserialize($data);
                if (is_array($data) && isset($data[0], $data[1]) && $data[0] === $name) {
                    $cookies[$name] = Yii::createObject([
                        'class' => 'yii\web\Cookie',
                        'name' => $name,
                        'value' => $data[1],
                        'expire' => null,
                    ]);
                }
            }
        } else {
            foreach ($this->_psr7->getCookieParams() as $name => $value) {
                $cookies[$name] = Yii::createObject([
                    'class' => 'yii\web\Cookie',
                    'name' => $name,
                    'value' => $value,
                    'expire' => null,
                ]);
            }
        }
        return $cookies;
    }

    /**
     * Retrieve attributes derived from the request.
     *
     * The request "attributes" may be used to allow injection of any
     * parameters derived from the request: e.g., the results of path
     * match operations; the results of decrypting cookies; the results of
     * deserializing non-form-encoded message bodies; etc. Attributes
     * will be application and request specific, and CAN be mutable.
     *
     * @return mixed[] Attributes derived from the request.
     */
    public function getAttributes()
    {
        return $this->_psr7->getAttributes();
    }

    /**
     * Retrieve a single derived request attribute.
     *
     * Retrieves a single derived request attribute as described in
     * getAttributes(). If the attribute has not been previously set, returns
     * the default value as provided.
     *
     * This method obviates the need for a hasAttribute() method, as it allows
     * specifying a default value to return if the attribute is not found.
     *
     * @see getAttributes()
     * @param string $name The attribute name.
     * @param mixed $default Default value to return if the attribute does not exist.
     * @return mixed
     */
    public function getAttribute($name, $default = null)
    {
        return $this->_psr7->getAttribute($name, $default);
    }
}
