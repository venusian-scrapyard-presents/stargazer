<?php

return [
    'event_loop' => [
        'pace_ms' => env('IO_POOLS_PACE_MS', 16),
        'mail_handlers' => [
            'default' => env('IO_POOLS_MAIL_HANDLER', 'signal'),
        ],
    ],
    'pool_waiters' => [
        'default' => env('IO_POOLS_WAITER_BACKEND', 'auto'),
        'drivers' => [
            'epoll' => [],
            'kqueue' => [],
            'select' => [],
        ]
    ],
    'promise_engines' => [
        'default' => env('IO_POOLS_PROMISE_ENGINE', 'guzzle'),
    ],
    'pool_workers' => [
        'process' => [
            'enabled' => true,
            'max_workers' => env('IO_POOLS_PROCESS_WORKERS', 4),
        ],
        'threads' => [
            'enabled' => false,
            'max_workers' => env('IO_POOLS_THREAD_WORKERS', 4),
        ]
    ]
];