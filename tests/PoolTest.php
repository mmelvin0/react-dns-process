<?php

namespace React\Tests\Dns\Process;

use Exception;
use PHPUnit\Framework\TestCase;
use React\Dns\Process\Pool;
use React\Dns\Process\Util;
use React\Dns\Resolver\Resolver;
use React\EventLoop;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use Throwable;

class PoolTest extends TestCase
{

    /**
     * @var LoopInterface;
     */
    protected $loop;

    /**
     * @var Pool
     */
    private $pool;

    public function setUp()
    {
        $this->loop = EventLoop\Factory::create();
        $this->pool = $this->createPool();;
        $this->pool->start();
    }

    public function tearDown()
    {
        $this->pool->stop();
    }

    public function testResolve()
    {
        if ($this->shouldSkip()) {
            $this->markTestSkipped();
        }
        $result = null;
        $error = null;
        $resolver = new Resolver('', $this->pool);
        $promise = $resolver->resolve('localhost')->then(
            function ($value) use (&$result) {
                $result = $value;
                $this->loop->stop();
            }, function ($reason) use (&$error) {
            $error = $reason;
            $this->loop->stop();
        }
        );
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $this->loop->addTimer(5, function () {
            throw new Exception('test timed out');
        });
        $this->loop->run();
        if ($error instanceof Exception || $error instanceof Throwable) {
            throw $error;
        }
        $this->assertNotFalse(filter_var($result, FILTER_VALIDATE_IP));
    }

    protected function createPool()
    {
        return new Pool($this->loop);
    }

    protected function shouldSkip()
    {
        return Util::isWindows();
    }

}
