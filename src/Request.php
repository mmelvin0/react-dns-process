<?php

namespace React\Dns\Process;

use InvalidArgumentException;
use JsonSerializable;
use React\Dns\Model\Message;
use React\Dns\Query\Query;
use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\PromisorInterface;

class Request implements JsonSerializable, PromisorInterface
{

    /**
     * @var Deferred
     */
    private $deferred;

    /**
     * @var Query
     */
    private $query;

    /**
     * @param Query $query
     */
    public function __construct(Query $query)
    {
        $this->deferred = new Deferred();
        $this->query = $query;
    }

    /**
     * @return Deferred
     */
    public function getDeferred()
    {
        return $this->deferred;
    }

    /**
     * @return Query
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @return int
     */
    public function getType()
    {
        switch ($this->query->type) {
            case Message::TYPE_A:
                return DNS_A;
            case Message::TYPE_CNAME:
                return DNS_CNAME;
            case Message::TYPE_MX:
                return DNS_MX;
            case Message::TYPE_NS:
                return DNS_NS;
            case Message::TYPE_PTR:
                return DNS_PTR;
            case Message::TYPE_SOA:
                return DNS_SOA;
            case Message::TYPE_TXT;
                return DNS_TXT;
            default:
                throw new InvalidArgumentException("unknown query type {$this->query->type}");
        }
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'name' => $this->query->name,
            'type' => $this->getType()
        ];
    }

    /**
     * @return ExtendedPromiseInterface
     */
    public function promise()
    {
        return $this->deferred->promise();
    }

}