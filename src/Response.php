<?php

namespace React\Dns\Process;

use InvalidArgumentException;
use React\Dns\Model\Message;
use React\Dns\Model\Record;

class Response
{

    /**
     * @var array
     */
    private $answers;

    /**
     * @param array $answers
     */
    public function __construct(array $answers)
    {
        $this->answers = $answers;
    }

    /**
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
                throw new InvalidArgumentException("unknown type {$answer->type}");
        }
    }

    /**
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
