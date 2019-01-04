<?php declare(strict_types=1);

namespace yii\Psr7\web;

use yii\Psr7\web\traits\Psr7ResponseTrait;

class Response extends \yii\web\Response
{
    use Psr7ResponseTrait;
}
