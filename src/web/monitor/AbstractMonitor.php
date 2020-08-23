<?php

declare(strict_types=1);

namespace yii\Psr7\web\monitor;

/**
 * Abstract implementation of a monitor
 * Monitors are similar to behaviors, with the exception that they are
 * only initialized a single time, and their state is reused and managed throughout
 * the application event loop
 */
abstract class AbstractMonitor
{
    /**
     * Enables the monitor
     *
     * @return void
     */
    abstract public function on() : void;

    /**
     * Disables the monitor
     *
     * @return void
     */
    abstract public function off() : void;

    /**
     * Shutdown and cleanup of the monitor
     *
     * @return void
     */
    abstract public function shutdown() : void;
}
