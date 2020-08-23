<?php

declare(strict_types=1);

namespace yii\Psr7\tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use yii\Psr7\web\Application;

class AbstractTestCase extends TestCase
{
    protected $app;

    protected $config;

    public function setUp(): void
    {
        $this->config = include __DIR__ . '/bootstrap.php';
        $this->app = new Application($this->config);
        $this->assertInstanceOf('\yii\Psr7\web\Application', $this->app);
    }
}
