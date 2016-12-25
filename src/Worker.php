<?php

namespace React\Dns\Process;

use RuntimeException;

class Worker
{

    /**
     * @return Worker
     */
    public static function fromEnvironment()
    {
        if (getenv('REACT_DNS_PROCESS_PORT')) {
            return SocketWorker::fromEnvironment();
        } else {
            return new static();
        }
    }

    public function run()
    {
        if (!is_resource(STDIN)) {
            throw new RuntimeException('unable to open standard input');
        }
        if (!is_resource(STDOUT)) {
            throw new RuntimeException('unable to open standard output');
        }
        stream_set_blocking(STDIN, false);
        stream_set_blocking(STDOUT, true);
        $this->loop(STDIN, STDOUT);
    }

    /**
     * @param resource $input
     * @param resource $output
     */
    public function loop($input, $output)
    {
        $buffer = '';
        while (1) {
            if (($data = $this->await($input)) === false) {
                break;
            }
            $buffer .= $data;
            while (($message = $this->next($buffer)) !== false) {
                if (($reply = $this->handle($message)) === false) {
                    break 2;
                } else {
                    $this->send($output, $reply);
                }
            }
        };
    }

    /**
     * @param resource $stream
     * @return int|bool
     */
    public function await($stream)
    {
        $read = [$stream];
        $write = $except = [];
        $result = stream_select($read, $write, $except, null);
        if ($result === false) {
            return false;
        }
        $data = stream_get_contents($stream);
        if ($data === false || strlen($data) === 0) {
            return false;
        }
        return $data;
    }

    /**
     * @param string $buffer
     * @return object|bool
     */
    public function next(&$buffer)
    {
        if (($pos = strpos($buffer, "\0")) === false) {
            return false;
        }
        $message = json_decode(substr($buffer, 0, $pos));
        $buffer = (string)substr($buffer, $pos + 1);
        return $message;
    }

    /**
     * @param object $message
     * @return string|bool
     */
    public function handle($message)
    {
        if (!isset($message->name) || !isset($message->type)) {
            return false;
        }
        return json_encode(['value' => $this->query($message->name, $message->type)]) . "\0";
    }

    /**
     * @param resource $stream
     * @param string $message
     */
    public function send($stream, $message)
    {
        fwrite($stream, $message);
    }

    /**
     * @param int $name
     * @param int $type
     * @return array
     */
    public function query($name, $type)
    {
        $answers = [];
        if ($type === DNS_A) {
            $address = gethostbyname($name);
            if (is_string($address) && $address !== $name) {
                $answers[] = [
                    'host' => $name,
                    'class' => 'IN',
                    'ttl' => 1,
                    'type' => 'A',
                    'ip' => $address
                ];
            }
        }
        if (empty($answers)) {
            $answers = dns_get_record($name, $type);
            if ($type === DNS_A) {
                $resolved = [];
                for ($i = 0; $i < count($answers); $i++) {
                    $answer = $answers[$i];
                    if ($answer['type'] === 'A' && $answer['host'] !== $name && !isset($resolved[$answer['host']])) {
                        $i -= $this->lookupCNAME($name, $answers, $i);
                        $resolved[$answer['host']] = 1;
                    } else if ($answer['type'] === 'CNAME' && !isset($resolved[$answer['target']])) {
                        $i -= $this->lookupCNAME($answer['target'], $answers, $i + 1);
                        $resolved[$answer['target']] = 1;
                    }
                }
            }
        }
        return $answers;
    }

    /**
     * @param string $name
     * @param array $answers
     * @param int $i
     * @return int
     */
    public function lookupCNAME($name, &$answers, $i) {
        $cnames = dns_get_record($name, DNS_CNAME);
        if (is_array($cnames)) {
            array_splice($answers, $i, 0, $cnames);
            return count($cnames);
        } else {
            return 0;
        }
    }

}
