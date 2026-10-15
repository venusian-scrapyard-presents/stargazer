<?php

use App\NutsAndBolts\DonkiChain;
use App\NutsAndBolts\DonkiEvent;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\GeomagneticStorm;

function donkiStub(string $id, string $type, array $links = [], string $label = ''): DonkiEvent
{
    return new DonkiEvent($id, $type, DonkiEvent::timeOfId($id), null, null, null, true, $label, $links, new stdClass);
}

it('lays a storm and everything linked to it out oldest first, with the gaps between', function () {
    $storm = DonkiEvent::from('GST', GeomagneticStorm::fromArray(stargazerFixture('donki-gst')[0]));
    $cme = donkiStub('2015-03-15T02:00:00-CME-001', 'CME', ['2015-03-15T01:48:00-FLR-001'], 'S22W25');
    $events = [$storm->id => $storm, $cme->id => $cme];

    $chain = DonkiChain::around($events, $storm->id);

    expect(array_column($chain, 'id'))->toBe([
        '2015-03-15T01:48:00-FLR-001',
        '2015-03-15T02:00:00-CME-001',
        '2015-03-16T00:00:00-HSS-001',
        '2015-03-17T04:05:00-IPS-001',
        '2015-03-17T06:00:00-GST-001',
    ])
        ->and(array_column($chain, 'loaded'))->toBe([false, true, false, false, true])
        ->and(array_column($chain, 'type'))->toBe(['FLR', 'CME', 'HSS', 'IPS', 'GST'])
        ->and($chain[0]['gap'])->toBeNull()
        ->and($chain[1]['gap'])->toBe(12 * 60)
        ->and($chain[4]['picked'])->toBeTrue()
        ->and($chain[1]['label'])->toBe('S22W25');
});

it('follows links in both directions from a record in the middle', function () {
    $flare = donkiStub('2015-03-15T01:48:00-FLR-001', 'FLR');
    $cme = donkiStub('2015-03-15T02:00:00-CME-001', 'CME', [$flare->id]);
    $shock = donkiStub('2015-03-17T04:05:00-IPS-001', 'IPS', [$cme->id]);

    $chain = DonkiChain::around([$flare->id => $flare, $cme->id => $cme, $shock->id => $shock], $cme->id);

    expect(array_column($chain, 'id'))->toBe([$flare->id, $cme->id, $shock->id]);
});

it('keeps the picked record when a chain is cut to its limit', function () {
    $events = [];
    $previous = null;
    for ($i = 0; $i < 30; $i++) {
        $id = sprintf('2015-03-%02dT00:00:00-CME-001', $i + 1);
        $events[$id] = donkiStub($id, 'CME', is_null($previous) ? [] : [$previous]);
        $previous = $id;
    }
    $picked = '2015-03-20T00:00:00-CME-001';

    $chain = DonkiChain::around($events, $picked);

    expect($chain)->toHaveCount(DonkiChain::LIMIT)
        ->and(array_column($chain, 'id'))->toContain($picked)
        ->and($chain[0]['gap'])->toBeNull();
});

it('leaves a record with no links alone', function () {
    $alone = donkiStub('2015-03-15T01:48:00-FLR-001', 'FLR');

    expect(DonkiChain::around([$alone->id => $alone], $alone->id))->toHaveCount(1);
});
