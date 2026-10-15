<?php

use App\NutsAndBolts\MapProjection;

it('puts a point where its longitude and latitude fall', function () {
    expect(MapProjection::fraction([0, 0]))->toBe([0.5, 0.5])
        ->and(MapProjection::fraction([-180, 90]))->toBe([0.0, 0.0])
        ->and(MapProjection::fraction([180, -90]))->toBe([1.0, 1.0])
        ->and(MapProjection::fraction([-90, 45]))->toBe([0.25, 0.25]);
});

it('places a polygon at the centroid of its outer ring', function () {
    $square = [[[10, 10], [20, 10], [20, 20], [10, 20], [10, 10]]];

    // centroid (15, 15)
    expect(MapProjection::fraction($square))->toBe([195 / 360, 75 / 180]);
});

it('clamps coordinates outside the globe', function () {
    expect(MapProjection::fraction([500, -400]))->toBe([1.0, 1.0]);
});

it('has no place for an empty geometry', function () {
    expect(MapProjection::fraction([]))->toBeNull();
});

it('places a polygon across the antimeridian at the antimeridian, not at Greenwich', function () {
    $pacific = [[[179, 10], [-179, 10], [-179, 20], [179, 20], [179, 10]]];

    // centroid (180, 15): the right edge
    expect(MapProjection::fraction($pacific))->toBe([1.0, 75 / 180]);
});
