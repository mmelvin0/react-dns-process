<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

React\Dns\Process\Worker::fromEnvironment()->run();
