<?php

namespace React\Dns\Process;

use InvalidArgumentException;
use LogicException;
use React\ChildProcess\Process;
use React\Dns\Model\Message;
use React\Dns\Query\ExecutorInterface;
use React\Dns\Query\Query;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Stream\Stream;
use RuntimeException;
use SplObjectStorage;
use SplQueue;

/**
 * DNS executor that runs queries in child processes.
 *
 * This pool communicates with its processes via stdin/stdout.
 */
class Pool implements ExecutorInterface
{

    const MAX_MESSAGE_SIZE = 8192;

    /**
     * Processes that are available for work.
     *
     * @var SplObjectStorage
     */
    protected $available;

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * Mapping of PID to process.
     *
     * @var Process[]
     */
    protected $processes = [];

    /**
     * Request queue.
     *
     * @var SplQueue
     */
    protected $queue;

    /**
     * Mapping of process to the request being handled by that process.
     *
     * @var SplObjectStorage
     */
    protected $requests;

    /**
     * @var bool
     */
    protected $running = false;

    /**
     * @var int
     */
    protected $size = 1;

    /**
     * Create a process pool executor.
     *
     * @param LoopInterface $loop Event loop to use.
     * @param int $size Number of processes to use.
     */
    public function __construct(LoopInterface $loop, $size = null)
    {
        $this->loop = $loop;
        if ($size !== null) {
            if (($size = filter_var($size, FILTER_VALIDATE_INT, ['min_range' => 1])) === false) {
                throw new InvalidArgumentException('size must be an integer >= 1');
            }
            $this->size = $size;
        }
        $this->available = new SplObjectStorage();
        $this->queue = new SplQueue();
        $this->requests = new SplObjectStorage();
    }

    /**
     * Start the process pool.
     */
    public function start()
    {
        if ($this->running) {
            throw new LogicException('already running');
        }
        $this->running = true;
        $this->spawn();
    }

    /**
     * Stop the process pool.
     */
    public function stop()
    {
        $this->running = false;
        foreach ($this->processes as $process) {
            $process->terminate();
        }
    }

    /**
     * Run a query.
     *
     * @inheritdoc
     * @param string $nameserver Ignored.
     * @param Query $query The query to execute.
     * @return PromiseInterface
     */
    public function query($nameserver, Query $query)
    {
        $request = new Request($query);
        $this->queue->enqueue($request);
        $this->flush();
        return $request->promise()->then(function (Response $response) use ($query) {
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

    /**
     * Flush queued requests to available workers.
     */
    public function flush()
    {
        foreach ($this->available as $worker) {
            if ($this->queue->isEmpty()) {
                break;
            }
            /** @var Request $request */
            $request = $this->queue->dequeue();
            $this->available->detach($worker);
            $this->requests->attach($worker, $request);
            $this->send($worker, json_encode($request) . "\0");
        }
    }

    /**
     * Send a message to a worker.
     *
     * @param object $worker
     * @param string $message
     */
    public function send($worker, $message)
    {
        if (!($worker instanceof Process)) {
            throw new InvalidArgumentException('worker must be a ' . Process::class);
        }
        if (!($worker->stdin instanceof Stream)) {
            throw new InvalidArgumentException('worker stdin must be a ' . Stream::class);
        }
        $worker->stdin->write($message);
    }

    /**
     * Retry a request that was being handled by a (failed) worker.
     *
     * @param object $worker
     */
    public function retry($worker)
    {
        if (!$this->requests->contains($worker)) {
            return;
        }
        $request = $this->requests[$worker];
        $this->requests->detach($worker);
        $this->queue->enqueue($request);
        $this->flush();
    }

    /**
     * Spawn child processes.
     */
    public function spawn()
    {
        while ($this->running && count($this->processes) < $this->size) {
            $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/bin/worker.php');
            $env = $this->createEnvironment();
            $process = new Process($command, null, $env, ['bypass_shell' => true]);
            $process->on('exit', function () use ($process) {
                $this->handleExit($process);
            });
            $process->start($this->loop);
            $this->postspawn($process, $env);
            if ($process->isRunning()) {
                $this->processes[$process->getPid()] = $process;
            }
        }
    }

    /**
     * Create environment variables for child process.
     *
     * @return array
     */
    public function createEnvironment()
    {
        $env = [];
        foreach (array_keys($_SERVER) as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $env[$key] = $value;
            }
        }
        return $env;
    }

    /**
     * Hook that runs after a single child process has been spawned.
     *
     * @param Process $process The process that was spawned.
     * @param array $env The environment used for the process.
     */
    public function postspawn(Process $process, array $env)
    {
        if (!($process->stdin instanceof Stream)) {
            throw new RuntimeException('process stdin must be a ' . Stream::class);
        }
        if (!($process->stdout instanceof Stream)) {
            throw new RuntimeException('process stdout must be a ' . Stream::class);
        }
        if ($process->isRunning()) {
            if ($process->stdout->isReadable()) {
                $process->stdout->on('data', $this->createOutputHandler($process));
            }
            $this->available->attach($process);
        }
    }

    /**
     * Destroy a worker.
     *
     * @param object $worker
     */
    public function despawn($worker)
    {
        if (!($worker instanceof Process)) {
            throw new InvalidArgumentException('worker must be a ' . Process::class);
        }
        $worker->terminate();
    }

    /**
     * Generate a callback to handle data from a worker.
     *
     * @param object $worker
     * @return callable
     */
    public function createOutputHandler($worker)
    {
        $buffer = '';
        return function ($data) use (&$buffer, $worker) {
            $buffer .= $data;
            while (($pos = strpos($buffer, "\0")) !== false) {
                $message = json_decode(substr($buffer, 0, $pos));
                $buffer = (string)substr($buffer, $pos + 1);
                $this->handleMessage($worker, $message);
            }
            if (strlen($buffer) > static::MAX_MESSAGE_SIZE) {
                $this->despawn($worker);
                $buffer = '';
            }
        };
    }

    /**
     * Handle exit of a child process.
     *
     * @param Process $process
     */
    public function handleExit(Process $process)
    {
        $this->available->detach($process);
        unset($this->processes[$process->getPid()]);
        $this->spawn();
        $this->retry($process);
    }

    /**
     * Handle a message from a worker.
     *
     * @param object $worker
     * @param object $message
     */
    public function handleMessage($worker, $message)
    {
        if ($this->requests->contains($worker)) {
            /** @var Request $request */
            $request = $this->requests[$worker];
            $this->requests->detach($worker);
            $deferred = $request->getDeferred();
            if (isset($message->value) || isset($message->reason)) {
                if (isset($message->value)) {
                    $deferred->resolve(new Response($message->value));
                } else {
                    $deferred->reject($message->reason);
                }
                $this->available->attach($worker);
            } else {
                $deferred->reject();
                $this->despawn($worker);
            }
        } else {
            $this->despawn($worker);
        }
        $this->flush();
    }

}
