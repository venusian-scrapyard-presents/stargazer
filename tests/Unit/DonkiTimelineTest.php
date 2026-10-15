<?php

use App\NutsAndBolts\DonkiEvent;
use App\NutsAndBolts\DonkiTimeline;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\Flare;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\GeomagneticStorm;

function donkiAt(string $id, string $type, array $links = [], ?int $end = null, ?float $magnitude = null): DonkiEvent
{
    return new DonkiEvent($id, $type, DonkiEvent::timeOfId($id), $end, null, $magnitude, true, '', $links, new stdClass);
}

it('gives each present type a lane, in DONKI order, and places events along the days', function () {
    $from = strtotime('2015-03-15Z');
    $to = strtotime('2015-03-18Z');
    $storm = DonkiEvent::from('GST', GeomagneticStorm::fromArray(stargazerFixture('donki-gst')[0]));
    $cme = donkiAt('2015-03-15T02:00:00-CME-001', 'CME');

    $timeline = new DonkiTimeline([$storm, $cme], $from, $to, 1000, 200);

    $lanes = $timeline->lanes();
    expect(array_column($lanes, 'type'))->toBe(['CME', 'GST'])
        ->and($lanes[0]['y'])->toBeLessThan($lanes[1]['y']);

    $markers = $timeline->markers();
    // Four days across the plot: the CME two hours in sits just right of the gutter, the storm bar runs to its last Kp.
    expect($markers[1]['type'])->toBe('CME')
        ->and($markers[1]['x'])->toBeGreaterThan(90)->toBeLessThan(112)
        ->and($markers[0]['shape'])->toBe('bar')
        ->and($markers[0]['x1'])->toBeGreaterThan($markers[0]['x'])
        ->and($markers[0]['x1'])->toBeLessThanOrEqual(1000)
        ->and($markers[0]['color'])->toBe('#b91c1c');
});

it('joins linked events that are both drawn, once', function () {
    $flare = donkiAt('2015-03-15T01:48:00-FLR-001', 'FLR', magnitude: 4.2);
    $cme = donkiAt('2015-03-15T02:00:00-CME-001', 'CME', [$flare->id]);
    $shock = donkiAt('2015-03-17T04:05:00-IPS-001', 'IPS', [$cme->id, '2015-03-16T00:00:00-HSS-001']);

    $timeline = new DonkiTimeline([$cme, $flare, $shock], strtotime('2015-03-15Z'), strtotime('2015-03-18Z'), 800, 240);

    expect($timeline->links())->toBe([[0, 1], [0, 2]])
        ->and($timeline->markers()[1]['shape'])->toBe('triangle')
        ->and($timeline->markers()[1]['color'])->toBe('#ef4444');
});

it('finds the event nearest a point, and nothing far from every event', function () {
    $flare = donkiAt('2015-03-15T12:00:00-FLR-001', 'FLR');
    $cme = donkiAt('2015-03-17T12:00:00-CME-001', 'CME');
    $timeline = new DonkiTimeline([$flare, $cme], strtotime('2015-03-15Z'), strtotime('2015-03-18Z'), 1000, 200);
    [$f, $c] = $timeline->markers();

    expect($timeline->nearest($f['x'] + 3, $f['y'] - 2))->toBe(0)
        ->and($timeline->nearest($c['x'], $c['y'] + 5))->toBe(1)
        ->and($timeline->nearest(900, 5))->toBeNull();
});

it('ticks every day over a short range and every week over a long one', function () {
    $flare = DonkiEvent::from('FLR', Flare::fromArray(stargazerFixture('donki-flr')[0]));
    $month = new DonkiTimeline([$flare], strtotime('2017-09-01Z'), strtotime('2017-09-30Z'), 1200, 200);
    $year = new DonkiTimeline([$flare], strtotime('2017-01-01Z'), strtotime('2017-12-31Z'), 1200, 200);

    // 1200 pixels across a year is 21 per week: every other week is labelled, so the labels never touch.
    expect($month->ticks()[0]['label'])->toBe('09-01')
        ->and($month->ticks()[1]['label'])->toBe('09-02')
        ->and($year->ticks()[0]['label'])->toBe('01-01')
        ->and($year->ticks()[1]['label'])->toBe('01-15')
        ->and($year->ticks()[1]['x'] - $year->ticks()[0]['x'])->toBeGreaterThanOrEqual(34);
});
