<?php declare(strict_types=1);

namespace yii\Psr7\tests;

use yii\Psr7\web\Application;
use Psr\Http\Message\ServerRequestInterface;
use PHPUnit\Framework\TestCase;

class AbstractTestCase extends TestCase
{
    private $_app;

    private $_config;

    public function init(ServerRequestInterface $request)
    {
        $config = $this->getConfig();
        $this->setApplication((new \yii\Psr7\web\Application($config, $request)));
    }

    public function getApplication()
    {
        return $this->_app;
    }

    public function setApplication(Application $app)
    {
        $this->_app = $app;
    }

    public function getConfig()
    {
        return $this->_config;
    }

    private function setConfig(array $config)
    {
        $this->_config = $config;
    }

    public function setUp()
    {
        $this->setConfig(require_once __DIR__ . '/bootstrap.php');
    }
}
