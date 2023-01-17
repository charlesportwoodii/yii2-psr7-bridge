<?php

namespace yii\Psr7\tests\controllers;

use Yii;
use yii\filters\auth\HttpBasicAuth;
use yii\web\Controller;
use yii\web\Cookie;
use yii\web\HttpException;
use yii\web\Response;

class SiteController extends Controller
{
    public function actionIndex()
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_JSON;
        return [
            'hello' => 'world',
        ];
    }

    public function actionStatuscode()
    {
        Yii::$app->response->statusCode = 201;
    }

    public function actionRedirect()
    {
        $response = Yii::$app->response->redirect('/site/index');
        return;
    }

    public function actionRefresh()
    {
        $response = Yii::$app->response->refresh('#foo');
        return;
    }

    public function actionPost()
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_JSON;
        return Yii::$app->request->post();
    }

    public function actionGet()
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_JSON;
        return Yii::$app->request->get();
    }

    public function actionCookie()
    {
        $response = Yii::$app->response;
        $response->cookies->add(
            new Cookie(
                [
                'name' => 'test',
                'value' => 'test',
                'httpOnly' => false
                ]
            )
        );

        $response->cookies->add(
            new Cookie(
                [
                'name' => 'test2',
                'value' => 'test2'
                ]
            )
        );
    }

    public function actionGetcookies()
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_JSON;
        return Yii::$app->request->getCookies();
    }

    public function actionAuth()
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_JSON;
        return [
            'username' => Yii::$app->request->getAuthUser(),
            'password' => Yii::$app->request->getAuthPassword()
        ];
    }

    public function action404()
    {
        throw new HttpException(404);
    }

    public function actionGeneralException()
    {
        throw new \Exception("General Exception");
    }

    public function actionQuery($test) {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_JSON;
        return [
            'test' => $test,
            'q' => Yii::$app->request->get('q'),
            'queryParams' => Yii::$app->request->getQueryParams()
        ];
    }

    public function actionStream() {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        if ($stream = fopen(__DIR__ . '/../.rr.yaml', 'r')) {
            return $response->sendStreamAsFile($stream, '.rr.yaml', [
                'mimeType' => 'text/yaml',
            ]);
        }
    }

    public function actionFile() {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        return $response->sendFile(__DIR__ . '/../.rr.yaml', '.rr.yaml', [
            'mimeType' => 'text/yaml',
        ]);
    }
}
