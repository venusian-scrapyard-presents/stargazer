<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Concurrency Driver
    |--------------------------------------------------------------------------
    |
    | The driver Concurrency's run() and async() use when none is named.
    | "process" runs each task in a fresh `php computer` process, "fork"
    | forks this one (spatie/fork), "sync" runs the tasks here one after
    | another, and "pool" sends them to IOPools' worker pools. Only the
    | "process" and "pool" drivers can run tasks without blocking.
    |
    | Supported drivers: "process", "fork", "sync", "pool"
    |
    */

    'default' => env('CONCURRENCY_DRIVER', 'process'),

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    |
    | The pool driver's "pool" picks the workers its tasks run on: "thread",
    | "process", or "auto" for the thread workers when they are on and the
    | process workers otherwise.
    |
    */

    'drivers' => [

        'pool' => [
            'driver' => 'pool',
            'pool' => env('CONCURRENCY_POOL', 'auto'),
        ],

    ],

];
