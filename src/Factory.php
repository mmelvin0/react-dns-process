<?php

namespace React\Dns\Process;

use React\Dns\Query\ExecutorInterface;
use React\Dns\Resolver\Factory as BaseFactory;
use React\EventLoop\LoopInterface;

class Factory extends BaseFactory
{

    /**
     * @param LoopInterface $loop
     * @return ExecutorInterface
     */
    public function createExecutor(LoopInterface $loop)
    {
        if (Util::isWindows()) {
            $pool = new SocketPool($loop);
        } else {
            $pool = new Pool($loop);
        }
        $pool->start();
        return $pool;
    }

}
