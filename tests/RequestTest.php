<?php

namespace React\Tests\Dns\Process;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use React\Dns\Model\Message;
use React\Dns\Process\Request;
use React\Dns\Query\Query;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

class RequestTest extends TestCase
{

    /**
     * @var Query
     */
    private $query;

    /**
     * @var Request
     */
    private $request;

    public function setUp()
    {
        $this->query = new Query('localhost', Message::TYPE_A, Message::CLASS_IN, time());
        $this->request = new Request($this->query);
    }

    public function testGetDeferred()
    {
        $this->assertInstanceOf(Deferred::class, $this->request->getDeferred());
    }

    public function testGetQuery()
    {
        $this->assertSame($this->query, $this->request->getQuery());
    }

    /**
     * @dataProvider getTypes
     * @param int $input
     * @param int $expected
     */
    public function testGetType($input, $expected)
    {
        $this->query->type = $input;
        $this->assertSame($expected, $this->request->getType());
    }

    public function getTypes()
    {
        return [
            'A' => [Message::TYPE_A, DNS_A],
            'CNAME' => [Message::TYPE_CNAME, DNS_CNAME],
            'MX' => [Message::TYPE_MX, DNS_MX],
            'NS' => [Message::TYPE_NS, DNS_NS],
            'PTR' => [Message::TYPE_PTR, DNS_PTR],
            'SOA' => [Message::TYPE_SOA, DNS_SOA],
            'TXT' => [Message::TYPE_TXT, DNS_TXT]
        ];
    }

    public function testGetTypeInvalid()
    {
        $this->query->type = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->request->getType();
    }

    public function testJsonSerialize()
    {
        $this->assertSame(json_encode(['name' => 'localhost', 'type' => DNS_A]), json_encode($this->request));
    }

    public function testPromise()
    {
        $promise = $this->request->promise();
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $this->assertSame($this->request->getDeferred()->promise(), $promise);
    }

}
