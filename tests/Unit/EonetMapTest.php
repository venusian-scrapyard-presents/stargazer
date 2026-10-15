<?php

use App\NutsAndBolts\EonetMap;

it('fits the largest 2:1 globe centred on the canvas', function () {
    expect((new EonetMap([], 800, 300))->globe())->toBe([100.0, 0.0, 600.0, 300.0])
        ->and((new EonetMap([], 600, 500))->globe())->toBe([0.0, 100.0, 600.0, 300.0]);
});

it('puts each dot where its event falls on the globe', function () {
    $map = new EonetMap([3 => [0.5, 0.5, 'wildfires'], 7 => [0.0, 1.0, null]], 800, 300);

    expect($map->position(3))->toBe([400.0, 150.0])
        ->and($map->position(7))->toBe([100.0, 300.0])
        ->and($map->position(5))->toBeNull();
});

it('picks the dot in reach of a click, scaled to device pixels', function () {
    $map = new EonetMap([0 => [0.5, 0.5, 'wildfires'], 1 => [0.25, 0.5, 'floods']], 800, 300, scale: 2.0);

    // reach is 10 points = 20 pixels at 2x
    expect($map->nearest(415.0, 150.0))->toBe(0)
        ->and($map->nearest(250.0, 160.0))->toBe(1)
        ->and($map->nearest(400.0, 175.0))->toBeNull();
});

it('gives a tie to the dot painted on top', function () {
    $map = new EonetMap([0 => [0.5, 0.5, 'wildfires'], 1 => [0.5, 0.5, 'volcanoes']], 800, 300);

    expect($map->nearest(400.0, 150.0))->toBe(1);
});

it('colours a dot by its category, and an unknown one grey', function () {
    expect(EonetMap::color('wildfires')->toHex())->toBe(Surface\NutsAndBolts\Color::hex(EonetMap::COLORS['wildfires'])->toHex())
        ->and(EonetMap::color(null)->toHex())->toBe(Surface\NutsAndBolts\Color::hex(EonetMap::OTHER)->toHex())
        ->and(EonetMap::color('somethingNew')->toHex())->toBe(Surface\NutsAndBolts\Color::hex(EonetMap::OTHER)->toHex());
});
