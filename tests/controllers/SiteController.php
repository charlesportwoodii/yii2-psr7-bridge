<?php

namespace yii\Psr7\tests\controllers;

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
        $response->cookies->add(new Cookie([
            'name' => 'test',
            'value' => 'test',
            'httpOnly' => true
        ]));

        $response->cookies->add(new Cookie([
            'name' => 'test2',
            'value' => 'test2'
        ]));

        return \array_merge([
            'hello' => 'world',
        ], Yii::$app->request->get());
    }
}
