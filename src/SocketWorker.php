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
        return new static(
            getenv('REACT_DNS_PROCESS_COOKIE'),
            'tcp://127.0.0.1:' . getenv('REACT_DNS_PROCESS_PORT')
        );
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
        $stream = stream_socket_client($this->host);
        if (!is_resource($stream)) {
            throw new RuntimeException("unable to connect to {$this->host}");
        }
        stream_set_blocking($stream, true);
        fwrite($stream, json_encode(['cookie' => $this->cookie]) . "\0");
        stream_set_blocking($stream, false);
        $buffer = '';
        while (1) {
            if (($data = $this->wait($stream)) === false) {
                break;
            }
            $buffer .= $data;
            while (($message = $this->next($buffer)) !== false) {
                if (($reply = $this->handle($message)) === false) {
                    break 2;
                } else {
                    stream_set_blocking($stream, true);
                    fwrite($stream, $reply);
                    stream_set_blocking($stream, false);
                }
            }
        };
        fclose($stream);
    }

}
