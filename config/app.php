<?php

use Voyager\NutsAndBolts\ServiceProvider;

return [
    /*
    |--------------------------------------------------------------------------
    | Application Name and ID
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */
    'name' => env('APP_NAME', 'Venusian'),
    'id' => 'com.venusian.stargazer',

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
     |--------------------------------------------------------------------------
     | Encryption Key
     |--------------------------------------------------------------------------
     |
     | This key is utilized by Venusian's encryption services and should be set
     | to a random, 32 character string to ensure that all encrypted values
     | are secure. You should do this prior to deploying the application.
     |
     */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on any
    | invocations to your application. You may add your own services to the
    | arrays below to provide additional features to this application.
    |
    */

    'providers' => ServiceProvider::defaultProviders()->merge([
        // Package Service Providers...
    ])->merge([
        // Application Service Providers...
        // App\Providers\AppServiceProvider::class,
    ])->merge([
        // Added Service Providers (Do not remove this line)...
    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Stargazer Application Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the application
    */
    'toolkit_bridge' => env('TOOLKIT_BRIDGE', 'gtk'),
    // PHP's memory limit for the app, as venusian/build's packaged runtime sets it; a zoomed
    // original (Webb's are 8000 px across, 170 MB decoded) needs more than PHP's 128M default.
    'memory_limit' => env('APP_MEMORY_LIMIT', '1024M'),
    'starting_resolution' => [
        'width' => 1024,
        'height' => 768,
    ],
    'timezone' => env('APP_TIMEZONE', 'America/New_York'),

];
