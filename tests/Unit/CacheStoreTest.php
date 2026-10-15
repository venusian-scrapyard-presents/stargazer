<?php

use App\NutsAndBolts\CacheStore;

it('starts on file with a Redis on this machine when nothing is stored', function () {
    expect(CacheStore::normalize([]))->toBe([
        'driver' => 'file',
        'host' => 'localhost',
        'port' => 6379,
        'password' => '',
        'database' => 1,
    ]);
});

it('keeps Redis settings typed in Settings', function () {
    expect(CacheStore::normalize(['driver' => 'redis', 'host' => ' cache.lan ', 'port' => '6380', 'password' => 's3cret', 'database' => '4']))->toBe([
        'driver' => 'redis',
        'host' => 'cache.lan',
        'port' => 6380,
        'password' => 's3cret',
        'database' => 4,
    ]);
});

it('falls back to the defaults for fields that are blank or out of range', function () {
    expect(CacheStore::normalize(['driver' => 'memcached', 'host' => '', 'port' => '70000', 'database' => '-1']))->toMatchArray([
        'driver' => 'file',
        'host' => 'localhost',
        'port' => 6379,
        'database' => 1,
    ])->and(CacheStore::normalize(['port' => 'abc', 'database' => 'x']))->toMatchArray(['port' => 6379, 'database' => 1]);
});
