<?php

declare(strict_types=1);

namespace yii\Psr7\web\monitor;

use Yii;
use yii\base\Event;

use yii\Psr7\web\monitor\AbstractMonitor;

/**
 * EventMonitor listens for any and all events so that they
 * can be gracefully unregistered from their associated class
 * when Application::terminate() is called, ensure that duplicate
 * events aren't repeatedly triggered on the next application loop.
 *
 * This has a slight performance impact on the main application, but
 * eliminates duplicate events on each subsequent page load, and is
 * negligible overall when use with a application runner such as RoadRunner.
 */
class EventMonitor extends AbstractMonitor
{
    protected $handler;

    protected $events = [];

    public function __construct()
    {
        $this->handler = function (Event $event) {
            $class = $event->sender;
            $this->events[] = $event;
        };
    }

    public function on() : void
    {
        Event::on('*', '*', $this->handler);
    }

    public function off() : void
    {
        Event::off('*', '*', $this->handler);
    }

    public function shutdown() : void
    {
        foreach (\array_reverse($this->events) as $event) {
            $event->sender->off($event->name);
        }

        $this->events = [];
        $this->off();

        if (method_exists(Event::class, 'offAll')) {
            Event::offAll();
        }
    }
}
