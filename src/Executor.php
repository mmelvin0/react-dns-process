<?php

namespace React\Dns\Process;

use React\Dns\Model\Message;
use React\Dns\Query\ExecutorInterface;
use React\Dns\Query\Query;

class Executor implements ExecutorInterface
{

    /**
     * @var PoolInterface
     */
    private $pool;

    /**
     * @param PoolInterface $pool
     */
    public function __construct(PoolInterface $pool)
    {
        $this->pool = $pool;
    }

    /**
     * @inheritdoc
     */
    public function query($nameserver, Query $query)
    {
        return $this->pool->send(new Request($query))
            ->then(function (Response $response) use ($query) {
                $message = new Message();
                $message->questions[] = [
                    'name' => $query->name,
                    'type' => $query->type,
                    'class' => $query->class
                ];
                $message->answers = $response->getAnswers();
                return $message;
            });
    }

}
