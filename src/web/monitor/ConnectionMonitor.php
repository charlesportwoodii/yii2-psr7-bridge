<?php

declare(strict_types=1);

namespace yii\Psr7\web\monitor;

use Yii;

use yii\base\Event;
use yii\db\Connection;
use yii\Psr7\web\Monitor;

/**
 * Handles any and all database connections that are established, and ensures
 * that they are closed at the end of each event loop.
 */
class ConnectionMonitor extends AbstractMonitor
{
    protected $handler;

    protected $connections = [];

    public function __construct()
    {
        $this->handler = function (Event $e) {
            if ($e->sender instanceof Connection) {
                $this->connections[] = $e->sender;
            }
        };
    }

    public function on() : void
    {
        Event::on(Connection::class, Connection::EVENT_AFTER_OPEN, $this->handler);
    }

    public function off() : void
    {
        Event::off(Connection::class, Connection::EVENT_AFTER_OPEN, $this->handler);
    }

    public function shutdown() : void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }

        $this->connections = [];


        $this->off();
    }
}
