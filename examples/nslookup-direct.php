<?php

use React\Dns\Process\Worker;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$worker = new Worker();
foreach (array_slice($_SERVER['argv'], 1) as $name) {
    $found = false;
    foreach ($worker->query($name, DNS_A) as $answer) {
        if ($answer['type'] === 'A' && $answer['host'] == $name) {
            print "$name: {$answer['ip']}" . PHP_EOL;
            $found = true;
        }
    }
    if (!$found) {
        print "$name: not found" . PHP_EOL;
    }
}
