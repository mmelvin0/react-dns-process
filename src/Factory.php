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
        return new Executor($this->createPool($loop));
    }

    /**
     * @param LoopInterface $loop
     * @param int $size
     * @return PoolInterface
     */
    public function createPool(LoopInterface $loop, $size = null)
    {
        if ($this->isWindows()) {
            $pool = new SocketPool($loop, $size);
        } else {
            $pool = new Pool($loop, $size);
        }
        $pool->start();
        return $pool;
    }

    /**
     * @return bool
     */
    public function isWindows()
    {
        return strcasecmp('win', strtolower(substr(PHP_OS, 0, 3))) === 0;
    }

}
