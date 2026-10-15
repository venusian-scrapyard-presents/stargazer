<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Venusian's queue reaches a variety of backends through one unified API,
    | so every backend is driven with identical syntax. The default connection
    | is defined below.
    |
    | This defaults to "sync": it needs no table and no server, which suits a
    | desktop or device app that hasn't chosen a backend yet.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every queue backend
    | your computer uses. An example configuration is provided for each
    | supported backend. You are free to add more.
    |
    | Drivers: "sync", "database", "redis", "deferred", "background",
    |          "failover", "null"
    |
    | Beanstalkd and SQS are not supported — see the driver policy in
    | .okf/known-gaps.md.
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],

        // in-process, on a worker-pool process or thread; push() returns a promise.
        // pool: 'thread' or 'process'; left out, the thread workers when they are on
        'background' => [
            'driver' => 'background',
            'pool' => env('QUEUE_BACKGROUND_POOL'),
        ],

        // in this process, on the loop's next turn; push() returns a promise
        'deferred' => [
            'driver' => 'deferred',
        ],

        'failover' => [
            'driver' => 'failover',
            'connections' => [
                'redis',
                'deferred',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options may be pointed at any connection
    | and table your computer has defined.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure how failed queue jobs are logged, and where they
    | are kept. The file driver needs no table; "database-uuids" keeps them in
    | the table queue:failed-table creates.
    |
    | Supported drivers: "database-uuids", "file", "null"
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'file'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

];
