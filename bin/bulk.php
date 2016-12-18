<?php

use React\Dns\Model\Message;
use React\Dns\Process\Executor;
use React\Dns\Process\SocketPool;
use React\Dns\Process\Request;
use React\Dns\Query\Query;
use React\Dns\Resolver\Resolver;
use React\EventLoop\Factory;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$hosts = [
    'www.apple.com',
    'www.reddit.com',
    'slashdot.org',
    'www.amazon.com',
    'www.microsoft.com',
    'www.google.com',
    'php.net',
    'www.python.org',
    'www.mozilla.org',
    'www.youtube.com'
];

$loop = Factory::create();
$pool = new SocketPool($loop);
$pool->start();
$executor = new Executor($pool);
$resolver = new Resolver('', $executor);
$timer = function () use ($pool, $resolver, $executor, $hosts, $loop, &$timer) {
    $promises = [];
    for ($i = 0; $i < 2000; $i++) {
        $name = $hosts[rand(0, count($hosts) - 1)];
        $promises[] = $resolver->resolve($name)->then(function ($ip) use ($name) {
            print "$name: $ip" . PHP_EOL;
        }, function ($reason) use ($name) {
            print "$name: not found ($reason)" . PHP_EOL;
        });
    }
    React\Promise\all($promises)->always(function () use ($loop, $timer) {
        $loop->addTimer(1, $timer);
    });

};

$loop->addTimer(0.001, $timer);
$loop->run();
