<?php

namespace React\Dns\Process;

use RuntimeException;

/**
 * Worker process that communicates via stdin/stdout.
 */
class Worker
{

    /**
     * Factory method to create a worker via environment variables.
     *
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

    /**
     * Run the worker.
     */
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
     * Worker input/output loop.
     *
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
     * Await data on a stream.
     *
     * @param resource $stream Stream to read from.
     * @return string|bool Data read from the stream, or false if the stream closes/errors.
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
     * Read the next message from a buffer.
     *
     * @param string $buffer Buffer to read from.
     * @return object|bool Message object or false if no message is currently available.
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
     * Handle a request message from the pool.
     *
     * @param object $message Message to handle.
     * @return string|bool Response message string or false if message is invalid.
     */
    public function handle($message)
    {
        if (!isset($message->name) || !isset($message->type)) {
            return false;
        }
        return json_encode(['value' => $this->query($message->name, $message->type)]) . "\0";
    }

    /**
     * Send a message on a stream.
     *
     * @param resource $stream Stream to send on.
     * @param string $message Message to send.
     */
    public function send($stream, $message)
    {
        fwrite($stream, $message);
    }

    /**
     * Perform DNS query.
     *
     * Uses dns_get_record() and shortcuts to gethostbyname() for A (address) queries.
     *
     * @param int $name The name to query.
     * @param int $type The type of query to perform.
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
     * Resolve a CNAME and add it to the answer list in the same order that React DNS does.
     *
     * @param string $name The name to lookup.
     * @param array $answers Current answer list.
     * @param int $i Index to insert new answers at.
     * @return int The number of records found.
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
