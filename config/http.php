<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Async drivers
    |--------------------------------------------------------------------------
    |
    | Which loop handler carries an async() request. "curl" uses ext-curl's
    | multi interface and owns the loop's sleep while requests are in
    | flight. "pcurl" needs the pcurl extension and hands curl's sockets to
    | the loop as streams. "auto" picks pcurl when it is loaded, curl
    | otherwise. Sync requests never touch a driver.
    | Available drivers: 'auto' | 'curl' | 'pcurl'
    */

    'async' => [
        'default' => env('HTTP_ASYNC_DRIVER', 'auto'),
        'drivers' => [
            'curl'  => ['driver' => 'curl',  'max_handles' => 50, 'multi_options' => []],
            'pcurl' => ['driver' => 'pcurl', 'max_handles' => 50],
        ],
    ],

];
