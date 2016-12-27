<?php

namespace React\Dns\Process;

use InvalidArgumentException;
use JsonSerializable;
use React\Dns\Model\Message;
use React\Dns\Query\Query;
use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\PromisorInterface;

/**
 * Represents a request from a pool to a worker.
 */
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
     * Create a request.
     *
     * @param Query $query The query associated with this request.
     */
    public function __construct(Query $query)
    {
        $this->deferred = new Deferred();
        $this->query = $query;
    }

    /**
     * Get the deferred associated with this request.
     *
     * @return Deferred
     */
    public function getDeferred()
    {
        return $this->deferred;
    }

    /**
     * Get the query associated with this request.
     *
     * @return Query
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Get the query type as expected by dns_get_record().
     *
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
     * Serialize the request to JSON.
     *
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
     * Get the promise associated with this request.
     *
     * @return ExtendedPromiseInterface
     */
    public function promise()
    {
        return $this->deferred->promise();
    }

}