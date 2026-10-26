<?php

use App\Services\GIBS\GIBSService;

it('moves the map with the pointer, north up', function () {
    // 10 points right and 4 down at 2× and 0.5 map units per pixel.
    expect(GIBSService::panned(100.0, 50.0, 10.0, 4.0, 2.0, 0.5))->toBe([90.0, 54.0]);
});

it('turns whole notches into zoom levels and keeps the rest', function () {
    expect(GIBSService::wheelLevels(0.0, 1.0))->toBe([1, 0.0])
        ->and(GIBSService::wheelLevels(0.0, -2.0))->toBe([-2, 0.0])
        ->and(GIBSService::wheelLevels(0.0, 0.4))->toBe([0, 0.4])
        ->and(GIBSService::wheelLevels(0.0, -0.6))->toBe([0, -0.6]);

    [$levels, $carry] = GIBSService::wheelLevels(0.4, 0.7);
    expect($levels)->toBe(1)->and($carry)->toEqualWithDelta(0.1, 1e-9);
});

it('drops a part-notch the other way when the wheel turns back', function () {
    // A zoom-out part-level of 0.9, then one notch in: the notch zooms in a whole level.
    expect(GIBSService::wheelLevels(0.9, -1.0))->toBe([-1, 0.0])
        ->and(GIBSService::wheelLevels(-0.5, 0.7))->toEqualWithDelta([0, 0.7], 1e-9);
});
