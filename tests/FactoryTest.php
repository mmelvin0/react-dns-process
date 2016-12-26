<?php

namespace React\Tests\Dns\Process;

use PHPUnit\Framework\TestCase;
use React\Dns\Process\Factory;
use React\Dns\Process\Pool;
use React\Dns\Query\ExecutorInterface;
use React\EventLoop;

class FactoryTest extends TestCase
{

    public function testCreateExecutor()
    {
        $loop = EventLoop\Factory::create();
        $factory = new Factory();
        $executor = $factory->createExecutor($loop);
        $this->assertInstanceOf(ExecutorInterface::class, $executor);
        if ($executor instanceof Pool) {
            $executor->stop();
        }
    }

}
