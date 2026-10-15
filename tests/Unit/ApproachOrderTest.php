<?php

use App\NutsAndBolts\ApproachOrder;
use ProjectSaturnStudios\Stargazer\NeoWs\DataObjects\CloseApproach;

function approach(string $date, ?string $full, ?int $epoch): CloseApproach
{
    return CloseApproach::fromArray(array_filter([
        'close_approach_date' => $date,
        'close_approach_date_full' => $full,
        'epoch_date_close_approach' => $epoch,
    ], fn ($value) => ! is_null($value)));
}

it('orders close approaches by time, not by the month name', function () {
    $october = approach('2026-10-30', '2026-Oct-30 10:00', 1793354400000);
    $november = approach('2026-11-02', '2026-Nov-02 10:00', 1793613600000);

    expect(ApproachOrder::key($october))->toBeLessThan(ApproachOrder::key($november));
});

it('falls back to the day when NeoWs sends no epoch', function () {
    expect(ApproachOrder::key(approach('2026-10-30', null, null)))->toBeLessThan(ApproachOrder::key(approach('2026-11-02', null, null)))
        ->and(ApproachOrder::key(null))->toBe(PHP_INT_MAX);
});
