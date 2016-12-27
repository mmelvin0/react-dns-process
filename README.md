# React DNS Process

Process-based executor for React DNS.


## What?

This project implements the `React\Dns\Query\ExecutorInterface` with a child process pool.

It resolves queries with `gethostbyname()` and `dns_get_record()` instead of sending them directly via TCP/UDP.

It should plug easily in to any project using `React\Dns\Factory` (instead use `React\Dns\Process\Factory`) and works on Windows.


## Why?

React DNS queries a single name server and does not use the system resolver. Thus it cannot:

+ Use the hosts file.
+ Resolve local network names without additional configuration.

This library enables those features and otherwise acts the same as `React\Dns`.


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
## Pool Management

To change the number of processes  used or to start/stop the process pool you'll need instantiate a pool directly.

Ensure you are using the correct class - `SocketPool` on Windows, `Pool` everywhere else - and then:

```php
$loop = React\EventLoop\Factory::create();
$executor = new React\Dns\Process\Pool($loop, 4); // will use 4 child processes
$executor->start(); // processes are started now
$resolve = new React\Dns\Resolver\Resolver('', $executor);
```

The first argument of the pool constructor is the React event loop to use and the second is the number of processes to spawn.

Also note that pool executors have `start()`/`stop()` methods. The executor won't function until it is started. The factory does this automatically.
