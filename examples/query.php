<?php

use React\Dns\Model\Message;
use React\Dns\Protocol\BinaryDumper;
use React\Dns\Protocol\Parser;
use React\Dns\Query\Executor;
use React\Dns\Query\Query;
use React\EventLoop;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$loop = EventLoop\Factory::create();
$executor = new Executor($loop, new Parser(), new BinaryDumper());

$opt = getopt('p:s:t:');
$port = isset($opt['p']) ? $opt['p'] : '53';
$server = isset($opt['s']) ? $opt['s'] : '8.8.8.8';
$type = isset($opt['t']) ? strtoupper(trim($opt['t'])) : 'A';
if (defined("DNS_$type") && defined(Message::class . "::TYPE_$type")) {
    $type1 = constant("DNS_$type");
    $type2 = constant(Message::class . "::TYPE_$type");
} else {
    throw new Exception("unknown type $type");
}

$dd = false;
$inopt = false;
foreach (array_slice($_SERVER['argv'], 1) as $name) {
    if ($name === '--') {
        $dd = true;
        continue;
    } else if (strpos($name, '-') === 0 && !$dd) {
        $inopt = true;
        continue;
    } else if ($inopt) {
        $inopt = false;
        continue;
    }

    print 'dns_get_record(' . json_encode($name) . ", DNS_$type):" . PHP_EOL;
    print '----' . PHP_EOL;
    print json_encode(dns_get_record($name, $type1), JSON_PRETTY_PRINT) . PHP_EOL;
    print '----' . PHP_EOL;

    print "react ($server:$port, " . json_encode($name) . ", Message::TYPE_$type):" . PHP_EOL;
    print '----' . PHP_EOL;
    $query = new Query($name, $type2, Message::CLASS_IN, time());
    $executor->query("$server:$port", $query)
        ->then(function (Message $message) {
            print json_encode((array)($message->answers), JSON_PRETTY_PRINT) . PHP_EOL;
        })
        ->otherwise(function ($reason) {
            print $reason . PHP_EOL;
        })
        ->always(function () use ($loop) {
            $loop->stop();
            print '----' . PHP_EOL;
        });
    $loop->run();
}
