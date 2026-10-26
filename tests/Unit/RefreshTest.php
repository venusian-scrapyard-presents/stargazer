<?php

use App\Enums\Outcome;
use App\NutsAndBolts\Busy;
use App\NutsAndBolts\StatusBar;
use App\RouteMap;
use Surface\Contracts\Windows\Primitives\TKLabel;
use Surface\Contracts\Windows\Primitives\TKSpinner;

function statusBar(array &$calls): StatusBar
{
    return new StatusBar(
        new Busy(Mockery::mock(TKSpinner::class)->shouldIgnoreMissing()),
        Mockery::mock(TKLabel::class)->shouldIgnoreMissing(),
        changed: function (string $screen, bool $busy) use (&$calls): void {
            $calls[] = [$screen, $busy];
        },
    );
}

it('refreshes an idle screen with a tab only', function () {
    expect(RouteMap::canRefresh('apod', false))->toBeTrue()
        ->and(RouteMap::canRefresh('apod', true))->toBeFalse()
        ->and(RouteMap::canRefresh('key', false))->toBeFalse();
});

it('tells the window when the shown screen starts and stops a run', function () {
    $calls = [];
    $bar = statusBar($calls);

    $bar->focus('apod');
    $bar->busy('apod', 'Loading…');
    $bar->settle('apod', Outcome::DONE, 'Done.');

    expect($calls)->toBe([['apod', false], ['apod', true], ['apod', false]])
        ->and($bar->isBusy('apod'))->toBeFalse();
});

it('reports the shown screen, not the screen whose run changed', function () {
    $calls = [];
    $bar = statusBar($calls);

    $bar->focus('epic');
    $bar->busy('apod', 'Loading…');

    expect($calls)->toBe([['epic', false], ['epic', false]])
        ->and($bar->isBusy('apod'))->toBeTrue()
        ->and($bar->isBusy('epic'))->toBeFalse();
});
