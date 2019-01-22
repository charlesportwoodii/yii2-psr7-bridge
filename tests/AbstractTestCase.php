<?php declare(strict_types=1);

namespace yii\Psr7\tests;

use yii\Psr7\web\Application;
use Psr\Http\Message\ServerRequestInterface;
use PHPUnit\Framework\TestCase;

class AbstractTestCase extends TestCase
{
    protected $app;

    protected $config;

    public function setUp()
    {
        $this->config = require __DIR__ . '/bootstrap.php';
        $this->app = new Application($this->config);
        $this->assertInstanceOf('\yii\Psr7\web\Application', $this->app);
    }
}
