<?php

namespace React\Dns\Process;

use InvalidArgumentException;
use React\Dns\Model\Message;
use React\Dns\Model\Record;

/**
 * Represents a response from a worker for a pool.
 */
class Response
{

    /**
     * @var array
     */
    private $answers;

    /**
     * Create a new response.
     *
     * @param array $answers Answers as returned by dns_get_record().
     */
    public function __construct(array $answers)
    {
        $this->answers = $answers;
    }

    /**
     * Get answers in a format suitable for a React DNS message.
     *
     * @return Record[]
     */
    public function getAnswers()
    {
        $answers = [];
        foreach ($this->answers as $answer) {
            $answers[] = new Record(
                $answer->host,
                $this->getType($answer->type),
                $this->getClass($answer->class),
                $answer->ttl,
                $this->getData($answer)
            );
        }
        return $answers;
    }

    /**
     * Get answer class in a format suitable for a React DNS message.
     *
     * @param string $class
     * @return int
     */
    public function getClass($class) {
        switch ($class) {
            case 'IN':
                return Message::CLASS_IN;
            default:
                throw new InvalidArgumentException("unknown class: $class");
        }
    }

    /**
     * Get the data for an answer given the answer type.
     *
     * This field is named differently by dns_get_record() but always called 'data' in a React DNS message.
     *
     * @param object $answer
     * @return string
     */
    public function getData($answer)
    {
        switch ($answer->type) {
            case 'A':
                return $answer->ip;
            case 'CNAME':
                return $answer->target;
            default:
                throw new InvalidArgumentException("unknown type: {$answer->type}");
        }
    }

    /**
     * Get answer type in a format suitable for a React DNS message.
     *
     * @param string $type
     * @return int
     */
    public function getType($type) {
        switch ($type) {
            case 'A':
                return Message::TYPE_A;
            case 'CNAME':
                return Message::TYPE_CNAME;
            case 'MX':
                return Message::TYPE_MX;
            case 'NS':
                return Message::TYPE_NS;
            case 'PTR':
                return Message::TYPE_PTR;
            case 'SOA':
                return Message::TYPE_SOA;
            case 'TXT':
                return Message::TYPE_TXT;
            default:
                throw new InvalidArgumentException("unknown type: $type");
        }
    }

}
