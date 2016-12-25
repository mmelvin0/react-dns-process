<?php

use React\Dns;
use React\EventLoop;
use React\Promise;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$factory = new Dns\Process\Factory();
$loop = EventLoop\Factory::create();
$resolver = $factory->create('', $loop);
$promises = [];
foreach (array_slice($_SERVER['argv'], 1) as $name) {
    $promises[] = $resolver->resolve($name)->then(
        function ($address) use ($name) {
            print "$name: $address" . PHP_EOL;
        },
        function ($reason) use ($name) {
            print "$name: not found ({$reason->getMessage()})" . PHP_EOL;
        }
    );
}
Promise\all($promises)->always([$loop, 'stop']);
$loop->run();
