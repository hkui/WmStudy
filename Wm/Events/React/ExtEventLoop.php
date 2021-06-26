<?php

namespace Wm\Events\React;

/**
 * Class ExtEventLoop
 * @package Workerman\Events\React
 */
class ExtEventLoop extends Base
{

    public function __construct()
    {
        $this->_eventLoop = new \React\EventLoop\ExtEventLoop();
    }
}
