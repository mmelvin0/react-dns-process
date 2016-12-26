<?php

namespace React\Tests\Dns\Process;

use React\Dns\Process\SocketPool;

class SocketPoolTest extends PoolTest
{

    protected function createPool()
    {
        return new SocketPool($this->loop);
    }

    protected function shouldSkip()
    {
        return false;
    }

}
