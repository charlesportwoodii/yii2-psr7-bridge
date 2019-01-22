<?php

namespace yii\Psr7\tests\controllers;

use yii\filters\auth\HttpBasicAuth;
use yii\web\Controller;
use yii\web\Response;
use yii\web\Cookie;
use Yii;

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
        $response->cookies->add(new Cookie([
            'name' => 'test',
            'value' => 'test',
            'httpOnly' => false
        ]));

        $response->cookies->add(new Cookie([
            'name' => 'test2',
            'value' => 'test2'
        ]));
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
}
