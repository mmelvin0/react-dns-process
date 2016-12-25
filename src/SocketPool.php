<?php

namespace React\Dns\Process;

use InvalidArgumentException;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Server;
use React\Stream\Stream;
use SplObjectStorage;

class SocketPool extends Pool
{

    /**
     * Mapping of Connection objects to PID.
     *
     * If a mapping is present here the connection is authenticated.
     *
     * @var SplObjectStorage
     */
    private $connections;

    /**
     * Mapping of cookie to PID associated with that cookie.
     *
     * @var string[]
     */
    private $cookies = [];

    /**
     * TCP port to listen on. Zero means randomly assign a port.
     *
     * @var int
     */
    private $port = 0;

    /**
     * @var Server
     */
    private $server;

    public function __construct(LoopInterface $loop, $size = null)
    {
        parent::__construct($loop, $size);
        $this->connections = new SplObjectStorage();
        $this->server = new Server($loop);
    }

    public function start()
    {
        if (!$this->running) {
            $this->server->on('connection', [$this, 'handleConnection']);
            $this->server->listen($this->port);
        }
        parent::start();
    }

    public function stop()
    {
        if ($this->running) {
            /** @var ConnectionInterface $connection */
            foreach ($this->connections as $connection) {
                $connection->close();
            }
            $this->server->shutdown();
        }
        parent::stop();
    }

    /**
     * @inheritdoc
     */
    public function write($worker, $data)
    {
        if (!($worker instanceof ConnectionInterface)) {
            throw new InvalidArgumentException('worker must be a ' . ConnectionInterface::class);
        }
        $worker->write($data);
    }

    /**
     * @inheritdoc
     */
    public function createEnvironment()
    {
        $env = parent::createEnvironment();
        $env['REACT_DNS_PROCESS_COOKIE'] = uniqid('', true);
        $env['REACT_DNS_PROCESS_PORT'] = $this->server->getPort();
        return $env;
    }

    /**
     * @inheritdoc
     */
    public function postspawn(Process $process, array $env)
    {
        foreach ([$process->stdin, $process->stdout, $process->stderr] as $stream) {
            if ($stream instanceof Stream) {
                $stream->close();
            }
        }
        if (isset($env['REACT_DNS_PROCESS_COOKIE'])) {
            $this->cookies[$env['REACT_DNS_PROCESS_COOKIE']] = $process->getPid();
        }
    }

    /**
     * @inheritdoc
     */
    public function despawn($worker)
    {
        if (!($worker instanceof ConnectionInterface)) {
            throw new InvalidArgumentException('worker must be a ' . ConnectionInterface::class);
        }
        $worker->close();
    }

    /**
     * @inheritdoc
     */
    public function handleExit(Process $process)
    {
        parent::handleExit($process);
        $pid = $process->getPid();
        /** @var ConnectionInterface $connection */
        foreach ($this->connections as $connection) {
            if ($this->connections[$connection] === $pid) {
                $this->retry($connection);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function handleMessage($worker, $message)
    {
        if ($this->connections->contains($worker)) {
            parent::handleMessage($worker, $message);
        } else if (isset($message->cookie) && isset($this->cookies[$message->cookie])) {
            $this->connections->attach($worker, $this->cookies[$message->cookie]);
            unset($this->cookies[$message->cookie]);
            $this->available->attach($worker);
            $this->flush();
        } else {
            $this->despawn($worker);
        }
    }

    /**
     * @param ConnectionInterface $connection
     */
    public function handleConnection(ConnectionInterface $connection)
    {
        $connection->on('close', [$this, 'handleClose']);
        $connection->on('data', $this->createOutputHandler($connection));
    }

    /**
     * @param ConnectionInterface $connection
     */
    public function handleClose(ConnectionInterface $connection)
    {
        $this->available->detach($connection);
        $this->connections->detach($connection);
        $this->retry($connection);
    }

}
