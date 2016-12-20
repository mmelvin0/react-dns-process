<?php

namespace React\Dns\Process;

use React\Promise\ExtendedPromiseInterface;

interface PoolInterface
{

    /**
     * @param Request $request
     * @return ExtendedPromiseInterface
     */
    public function send(Request $request);

}
