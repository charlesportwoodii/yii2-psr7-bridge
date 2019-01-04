<?php

namespace yii\Psr7\tests;

use yii\Psr7\tests\AbstractTestCase;

use Zend\Diactoros\ServerRequestFactory;

class ApplicationTest extends AbstractTestCase
{
    public function testInit()
    {
        $psr7Request = ServerRequestFactory::fromGlobals(
            [
                'DOCUMENT_ROOT' => '',
                'REMOTE_ADDR' => '127.0.0.1',
                'SCRIPT_NAME' => basename(__FILE__),
                'PHP_SELF' => basename(__FILE__),
                'SCRIPT_FILENAME' => __DIR__ . '/' . basename(__FILE__),
            ],
            ['id' => '10', 'user' => 'foo'],
            null,
            null,
            null
        );

        $this->init($psr7Request);
        $app = $this->getApplication();
        $response = $app->handlePsr7Request();
    }
}
