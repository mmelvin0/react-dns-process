<?php

namespace React\Dns\Process;

use InvalidArgumentException;
use RuntimeException;

class SocketWorker extends Worker
{

    /**
     * @var string
     */
    private $cookie;

    /**
     * @var string
     */
    private $host;

    /**
     * @return SocketWorker
     */
    public static function fromEnvironment()
    {
        return new static(getenv('REACT_DNS_PROCESS_COOKIE'), 'tcp://127.0.0.1:' . getenv('REACT_DNS_PROCESS_PORT'));
    }

    /**
     * @param string $cookie
     * @param string $host
     */
    public function __construct($cookie, $host)
    {
        if (!is_string($cookie)) {
            throw new InvalidArgumentException('cookie must be a string');
        }
        if (!is_string($host)) {
            throw new InvalidArgumentException('host must be a string');
        }
        $this->cookie = $cookie;
        $this->host = $host;
    }

    public function run()
    {
        $socket = stream_socket_client($this->host);
        if (!is_resource($socket)) {
            throw new RuntimeException("unable to connect to {$this->host}");
        }
        $this->send($socket, json_encode(['cookie' => $this->cookie]) . "\0");
        $this->loop($socket, $socket);
        fclose($socket);
    }

    /**
     * @inheritdoc
     */
    public function send($stream, $message)
    {
        stream_set_blocking($stream, true);
        fwrite($stream, $message);
        stream_set_blocking($stream, false);
    }

}
