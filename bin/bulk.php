<?php

use React\Dns\Process;
use React\EventLoop;

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

$loop = EventLoop\Factory::create();
$factory = new Process\Factory();
$resolver = $factory->create('', $loop);
$timer = function () use ($resolver, $hosts, $loop, &$timer) {
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
$loop->nextTick($timer);
$loop->run();
