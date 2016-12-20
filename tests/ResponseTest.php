<?php

namespace React\Tests\Dns\Process;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use React\Dns\Model\Message;
use React\Dns\Model\Record;
use React\Dns\Process\Response;

class ResponseTest extends TestCase
{

    /**
     * @var Response
     */
    private $response;

    public function setUp()
    {
        $this->response = new Response([
            (object)([
                'host' => 'localhost',
                'type' => 'A',
                'class' => 'IN',
                'ttl' => 1,
                'ip' => '127.0.0.1'
            ])
        ]);
    }

    public function testGetAnswers()
    {

        $answers = $this->response->getAnswers();
        $this->assertCount(1, $answers);
        $this->assertInstanceOf(Record::class, $answers[0]);
        $this->assertSame('localhost', $answers[0]->name);
        $this->assertSame(Message::TYPE_A, $answers[0]->type);
        $this->assertSame(Message::CLASS_IN, $answers[0]->class);
        $this->assertSame(1, $answers[0]->ttl);
        $this->assertSame('127.0.0.1', $answers[0]->data);
    }

    public function testGetClass()
    {
        $this->assertSame(Message::CLASS_IN, $this->response->getClass('IN'));
    }

    public function testGetClassInvalid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->response->getClass('invalid');
    }

    /**
     * @dataProvider provideData
     */
    public function testGetData($input, $expected)
    {
        $this->assertSame($expected, $this->response->getData($input));
    }

    public function provideData()
    {
        return [
            'A' => [
                (object)([
                    'type' => 'A',
                    'ip' => '127.0.0.1'
                ]),
                '127.0.0.1'
            ],
            'CNAME' => [
                (object)([
                    'type' => 'CNAME',
                    'target' => 'localhost'
                ]),
                'localhost'
            ]
        ];
    }

    public function testGetDataInvalid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->response->getData((object)(['type' => 'invalid']));
    }

    /**
     * @dataProvider getTypes
     * @param $input
     * @param $expected
     */
    public function testGetType($input, $expected)
    {
        $this->assertSame($expected, $this->response->getType($input));
    }

    public function testGetTypeInvalid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->response->getType('invalid');
    }

    public function getTypes()
    {
        return [
            ['A', Message::TYPE_A],
            ['CNAME', Message::TYPE_CNAME],
            ['MX', Message::TYPE_MX],
            ['NS', Message::TYPE_NS],
            ['PTR', Message::TYPE_PTR],
            ['SOA', Message::TYPE_SOA],
            ['TXT', Message::TYPE_TXT]
        ];
    }

}
