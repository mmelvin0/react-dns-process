<?php

namespace React\Tests\Dns\Process;

use PHPUnit\Framework\TestCase;
use React\Dns\Process\Worker;

class WorkerTest extends TestCase
{

    /**
     * @var Worker
     */
    private $worker;

    public function setUp()
    {
        $this->worker = new Worker();
    }

    public function testQuery()
    {
        $result = $this->worker->query('localhost', DNS_A);
        $this->assertTrue(is_array($result));
    }

}
