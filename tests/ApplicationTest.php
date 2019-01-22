<?php

namespace yii\Psr7\tests;

use yii\Psr7\tests\AbstractTestCase;

use Zend\Diactoros\ServerRequestFactory;

class ApplicationTest extends AbstractTestCase
{
    /**
     * Tests Yii::$app->response->format with a simple JSON response
     */
    public function testIndexWithJsonResponse()
    {
        $request = ServerRequestFactory::fromGlobals();

        $response = $this->app->handle($request);

        $this->assertInstanceOf('\Psr\Http\Message\ResponseInterface', $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=UTF-8', $response->getHeaders()['Content-Type'][0]);

        $body = $response->getBody()->getContents();
        $this->assertEquals('{"hello":"world"}', $body);
    }

    /**
     * Tests Yii::$app->response->setStatusCode()
     */
    public function testCustomStatusCode()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => 'site/statuscode',
            'REQUEST_METHOD' => 'GET'
        ]);

        $response = $this->app->handle($request);

        $this->assertInstanceOf('\Psr\Http\Message\ResponseInterface', $response);
        $this->assertEquals(201, $response->getStatusCode());
    }

    /**
     * Tests HTTP 302 redirects
     */
    public function testRedirect()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => 'site/redirect',
            'REQUEST_METHOD' => 'GET'
        ]);

        $response = $this->app->handle($request);

        $this->assertInstanceOf('\Psr\Http\Message\ResponseInterface', $response);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/site/index', $response->getHeaders()['Location'][0]);
    }

    /**
     * Tests Yii::$app->response->refresh() with URI fragment
     */
    public function testFragment()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => 'site/refresh',
            'REQUEST_METHOD' => 'GET'
        ]);

        $response = $this->app->handle($request);

        $this->assertInstanceOf('\Psr\Http\Message\ResponseInterface', $response);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('site/refresh#foo', $response->getHeaders()['Location'][0]);
    }

    /**
     * Sends GET request with a Query string and verify the JSON response matches
     */
    public function testGetWithQueryParams()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => 'site/get',
            'REQUEST_METHOD' => 'GET'
        ],
        [
            'foo' => 'bar',
            'a' => [
                'b' => 'c'
            ]
        ]);

        $response = $this->app->handle($request);

        $this->assertInstanceOf('\Psr\Http\Message\ResponseInterface', $response);
        $this->assertEquals(200, $response->getStatusCode());

        $body = $response->getBody()->getContents();
        $this->assertEquals('{"foo":"bar","a":{"b":"c"}}', $body);
    }

    /**
     * Sends POST request and verify the JSON response matches
     */
    public function testPostWithRequestBody()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => 'site/post',
            'REQUEST_METHOD' => 'POST'
        ],
        null,
        [
            'foo' => 'bar',
            'a' => [
                'b' => 'c'
            ]
        ]);

        $response = $this->app->handle($request);

        $this->assertInstanceOf('\Psr\Http\Message\ResponseInterface', $response);
        $this->assertEquals(200, $response->getStatusCode());

        $body = $response->getBody()->getContents();
        $this->assertEquals('{"foo":"bar","a":{"b":"c"}}', $body);
    }

    /**
     * Tests that cookie headers are set
     */
    public function testSetCookie()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => 'site/cookie',
            'REQUEST_METHOD' => 'GET'
        ]);

        $response = $this->app->handle($request);

        $this->assertInstanceOf('\Psr\Http\Message\ResponseInterface', $response);
        $this->assertEquals(200, $response->getStatusCode());
        $cookies = $response->getHeaders()['Set-Cookie'];
        foreach ($cookies as $i => $cookie) {
            // Skip the PHPSESSION header
            if ($i+1 == count($cookies)) {
                continue;
            }
            $params = \explode('; ', $cookie);
            $this->assertTrue(\in_array($params[0], [
                'test=test',
                'test2=test2'
            ]));
        }
    }

    public function testGetCookie()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => 'site/getcookies',
            'REQUEST_METHOD' => 'GET'
        ],
        null,
        null,
        [
            'test=test; HttpOnly; Path=/'
        ]);

        $response = $this->app->handle($request);

        $this->assertInstanceOf('\Psr\Http\Message\ResponseInterface', $response);
        $this->assertEquals(200, $response->getStatusCode());
        var_dump($body = $response->getBody()->getContents());
    }
}
