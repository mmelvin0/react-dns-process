<?php

namespace React\Tests\Dns\Process;

use Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as Mock;
use React\Dns\Model\Message;
use React\Dns\Process\Executor;
use React\Dns\Process\PoolInterface;
use React\Dns\Process\Request;
use React\Dns\Process\Response;
use React\Dns\Query\Query;
use React\Promise;
use React\Promise\PromiseInterface;
use Throwable;

class ExecutorTest extends TestCase
{

    /**
     * @var Executor
     */
    private $executor;

    /**
     * @var Mock|PoolInterface
     */
    private $pool;

    public function setUp()
    {
        $this->pool = $this->createMock(PoolInterface::class);
        $this->executor = new Executor($this->pool);
    }

    public function testQuery()
    {
        $query = new Query('localhost', Message::TYPE_A, Message::CLASS_IN, time());
        $response = new Response([
            (object)([
                'host' => 'localhost',
                'type' => 'A',
                'class' => 'IN',
                'ttl' => 1,
                'ip' => '127.0.0.1'
            ])
        ]);

        $this->pool->expects($this->once())->method('send')
            ->with($this->isInstanceOf(Request::class))
            ->willReturn(Promise\resolve($response));

        $promise = $this->executor->query('', $query);
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        /** @var Message $message */
        $message = null;
        $error = null;
        $promise->then(
            function ($value) use (&$message) { $message = $value; },
            function ($reason) use (&$error) { $error = $reason; }
        );
        if ($error instanceof Exception || $error instanceof Throwable) {
            throw $error;
        } else if ($error !== null) {
            trigger_error($error);
        }

        $this->assertInstanceOf(Message::class, $message);
        $this->assertCount(1, $message->questions);
        $this->assertSame('localhost', $message->questions[0]['name']);
        $this->assertSame(Message::TYPE_A, $message->questions[0]['type']);
        $this->assertSame(Message::CLASS_IN, $message->questions[0]['class']);
        $this->assertSame('localhost', $message->answers[0]->name);
        $this->assertSame(Message::TYPE_A, $message->answers[0]->type);
        $this->assertSame(Message::CLASS_IN, $message->answers[0]->class);
        $this->assertSame(1, $message->answers[0]->ttl);
        $this->assertSame('127.0.0.1', $message->answers[0]->data);
    }

}
