<?php

use React\Dns\Process\Executor;
use React\Dns\Process\SocketPool;
use React\Dns\Resolver\Resolver;
use React\EventLoop\Factory;
use React\Promise;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$loop = Factory::create();
$pool = new SocketPool($loop);
$pool->start();
$executor = new Executor($pool);
$resolver = new Resolver('', $executor);
$promises = [];
foreach (array_slice($_SERVER['argv'], 1) as $name) {
    $promises[] = $resolver->resolve($name)->then(
        function ($address) use ($name) {
            print "$name: $address" . PHP_EOL;
        },
        function ($reason) use ($name) {
            print "$name: not found ($reason)" . PHP_EOL;
        }
    );
}
Promise\all($promises)->always([$pool, 'stop']);
$loop->run();
