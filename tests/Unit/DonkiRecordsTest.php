<?php

use App\NutsAndBolts\DonkiRecords;
use App\NutsAndBolts\DonkiRows;

dataset('donki types', [
    'CME' => ['CME', 'donki-cme'],
    'CME Analysis' => ['CME Analysis', 'donki-cmeAnalysis'],
    'GST' => ['GST', 'donki-gst'],
    'IPS' => ['IPS', 'donki-ips'],
    'FLR' => ['FLR', 'donki-flr'],
    'SEP' => ['SEP', 'donki-sep'],
    'MPC' => ['MPC', 'donki-mpc'],
    'RBE' => ['RBE', 'donki-rbe'],
    'HSS' => ['HSS', 'donki-hss'],
    'WSA-Enlil' => ['WSA-Enlil', 'donki-wsaEnlilSimulations'],
    'Notifications' => ['Notifications', 'donki-notifications'],
]);

it('covers every DONKI type', function () {
    expect(array_keys(DonkiRecords::CLASSES))->toBe(array_keys(DonkiRows::TYPES));
});

it('stores a record so it comes back the same', function (string $type, string $fixture) {
    foreach (stargazerFixture($fixture) as $data) {
        $record = DonkiRecords::CLASSES[$type]::fromArray($data);
        $stored = DonkiRecords::encode($record);
        $back = DonkiRecords::decode($type, $stored);

        expect($back)->toBeInstanceOf(DonkiRecords::CLASSES[$type])
            ->and(DonkiRecords::encode($back))->toBe($stored)
            ->and(DonkiRows::row($type, $back))->toBe(DonkiRows::row($type, $record))
            ->and(DonkiRecords::key($type, $back))->toBe(DonkiRecords::key($type, $record));
    }
})->with('donki types');

it('keys a CME analysis by its own link, not its CME', function () {
    $analysis = DonkiRecords::decode('CME Analysis', json_encode(stargazerFixture('donki-cmeAnalysis')[0]));

    expect(DonkiRecords::key('CME Analysis', $analysis))->toBe('https://webtools.ccmc.gsfc.nasa.gov/DONKI/view/CMEAnalysis/11233/-1')
        ->and(DonkiRows::row('CME Analysis', $analysis)['time'])->toBe('2016-09-06T08:54Z');
});

it('asks only for the days not held', function () {
    $now = strtotime('2026-10-09T12:00:00Z');
    $settled = strtotime('2026-09-20T00:00:00Z');

    // Settled days are held for good; a day fetched long ago but within a week of its end is asked again.
    expect(DonkiRecords::missingSpan(['2026-09-01' => $settled, '2026-09-02' => $settled], '2026-09-01', '2026-09-02', $now))->toBeNull()
        ->and(DonkiRecords::missingSpan(['2026-09-01' => $settled, '2026-09-02' => strtotime('2026-09-04T00:00:00Z')], '2026-09-01', '2026-09-02', $now))->toBe(['2026-09-02', '2026-09-02'])
        // Recent days fetched within the hour are reused; an hour on, they are asked again.
        ->and(DonkiRecords::missingSpan(['2026-10-08' => $now - 600, '2026-10-09' => $now - 600], '2026-10-08', '2026-10-09', $now))->toBeNull()
        ->and(DonkiRecords::missingSpan(['2026-10-08' => $now - 7200, '2026-10-09' => $now - 600], '2026-10-08', '2026-10-09', $now))->toBe(['2026-10-08', '2026-10-08'])
        // Gaps make one span from the first missing day to the last.
        ->and(DonkiRecords::missingSpan(['2026-09-02' => $settled], '2026-09-01', '2026-09-03', $now))->toBe(['2026-09-01', '2026-09-03'])
        ->and(DonkiRecords::missingSpan([], '2026-09-01', '2026-09-30', $now))->toBe(['2026-09-01', '2026-09-30']);
});

it('lists every day of a range', function () {
    expect(DonkiRecords::days('2026-02-27', '2026-03-02'))->toBe(['2026-02-27', '2026-02-28', '2026-03-01', '2026-03-02'])
        ->and(DonkiRecords::days('2026-03-02', '2026-03-02'))->toBe(['2026-03-02']);
});
