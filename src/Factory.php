<?php

namespace React\Dns\Process;

use React\Dns\Query\ExecutorInterface;
use React\Dns\Resolver;
use React\EventLoop\LoopInterface;

/**
 * Extend the React DNS factory to create process executors.
 */
class Factory extends Resolver\Factory
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
