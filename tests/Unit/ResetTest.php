<?php

use App\NutsAndBolts\Reset;

it('empties every app table but the migrations ledger', function () {
    expect(Reset::tables(['migrations', 'persistent_variables', 'apod_days', 'donki_days', 'donki_records', 'image_items']))
        ->toBe(['persistent_variables', 'apod_days', 'donki_days', 'donki_records', 'image_items']);
});

it('flushes Redis only when the stored store is Redis', function () {
    expect(Reset::flushesRedis(['driver' => 'redis']))->toBeTrue()
        ->and(Reset::flushesRedis(['driver' => 'file']))->toBeFalse();
});

it('names the confirm alert', function () {
    $alert = Reset::alert();

    expect($alert->buttons)->toBe(['Reset', 'Cancel'])
        ->and($alert->default)->toBe(1)
        ->and($alert->cancel)->toBe(1)
        ->and(Reset::confirmed(0))->toBeTrue()
        ->and(Reset::confirmed(1))->toBeFalse()
        ->and(Reset::confirmed(-1))->toBeFalse();
});
