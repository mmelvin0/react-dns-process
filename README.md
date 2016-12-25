# React DNS Process

Process-based executor for React DNS.


## What?

This project implements the `React\Dns\Query\ExecutorInterface` with a child process pool. By default it spawns one worker process.

It resolves queries with `gethostbyname()` and `dns_get_record()` instead of sending them directly to a single server.

It should plug easily in to any project using `React\Dns\Factory` (instead use `React\Dns\Process\Factory`.)

It works on Windows too.


## Why?

React DNS queries a single name server and does not use the system resolver. Thus it cannot:

+ Use the hosts file.
+ Resolve local network names without additional configuration.

React DNS Process enables these and otherwise acts the same as `React\Dns`.


## Basic Usage

To resolve a name:

```php
$factory = new React\Dns\Process\Factory();
$loop = React\EventLoop\Factory::create();
$resolver = $factory->create('', $loop); // nameserver is not used
$name = 'www.google.com';
$resolver->resolve($name)->then(function ($address) use ($loop, $name) {
    print "$name: $address" . PHP_EOL;
    $loop->stop();
});
$loop->run();
```

The above is exactly equivalent to this synchronous vanilla PHP:

```php
$name = 'www.google.com';
$address = gethostbyname($name);
print "$name: $address" . PHP_EOL;
```
