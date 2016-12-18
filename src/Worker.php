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
        $stdin = fopen('php://stdin', 'r');
        $stdout = fopen('php://stdout', 'w');
        if (!is_resource($stdin)) {
            throw new RuntimeException('unable to open stdin');
        }
        stream_set_blocking($stdin, false);
        stream_set_blocking($stdout, true);
        $buffer = '';
        while (1) {
            if (($data = $this->wait($stdin)) === false) {
                break;
            }
            $buffer .= $data;
            while (($message = $this->next($buffer)) !== false) {
                if (($reply = $this->handle($message)) === false) {
                    break 2;
                } else {
                    fwrite($stdout, $reply);
                }
            }
        };
        fclose($stdin);
        fclose($stdout);
    }

    /**
     * @param resource $input
     * @return int|bool
     */
    public function wait($input)
    {
        $read = [$input];
        $write = $except = [];
        $result = stream_select($read, $write, $except, null);
        if ($result === false) {
            return false;
        }
        $data = stream_get_contents($input);
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
                        $i -= $this->lookupCname($name, $answers, $i);
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
    public function lookupCname($name, &$answers, $i) {
        $cnames = dns_get_record($name, DNS_CNAME);
        if (is_array($cnames)) {
            array_splice($answers, $i, 0, $cnames);
            return count($cnames);
        } else {
            return 0;
        }
    }

}
