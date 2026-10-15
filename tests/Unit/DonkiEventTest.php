<?php

use App\NutsAndBolts\DonkiEvent;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\CmeAnalysis;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\Flare;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\GeomagneticStorm;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\Notification;

it('reads a flare as a span with its class as a magnitude', function () {
    $event = DonkiEvent::from('FLR', Flare::fromArray(stargazerFixture('donki-flr')[0]));

    expect($event->id)->toBe('2017-09-06T08:57:00-FLR-001')
        ->and($event->time)->toBe(strtotime('2017-09-06T08:57Z'))
        ->and($event->end)->toBe(strtotime('2017-09-06T09:17Z'))
        ->and($event->magnitude)->toBe(4.22)
        ->and($event->label)->toBe('X2.2')
        ->and($event->links)->toBe([]);
});

it('reads a storm as running to its last Kp reading, at its peak Kp, linked to its causes', function () {
    $event = DonkiEvent::from('GST', GeomagneticStorm::fromArray(stargazerFixture('donki-gst')[0]));

    expect($event->time)->toBe(strtotime('2015-03-17T06:00Z'))
        ->and($event->end)->toBe(strtotime('2015-03-18T18:00Z'))
        ->and($event->magnitude)->toBe(8.0)
        ->and($event->links)->toBe(['2015-03-15T02:00:00-CME-001', '2015-03-17T04:05:00-IPS-001', '2015-03-16T00:00:00-HSS-001']);
});

it('reads a CME analysis under its CME with its speed, linked to that CME', function () {
    $event = DonkiEvent::from('CME Analysis', CmeAnalysis::fromArray(stargazerFixture('donki-cmeAnalysis')[0]));

    expect($event->id)->toBe('2016-09-06T08:54:00-CME-001')
        ->and($event->time)->toBe(strtotime('2016-09-06T08:54Z'))
        ->and($event->magnitude)->toBe(674.0)
        ->and($event->firm)->toBeTrue()
        ->and($event->links)->toBe([]);
});

it('reads a notification by its issue time with no links', function () {
    $event = DonkiEvent::from('Notifications', Notification::fromArray(stargazerFixture('donki-notifications')[0]));

    expect($event->time)->toBe(strtotime('2014-05-08T12:43Z'))
        ->and($event->links)->toBe([])
        ->and(DonkiEvent::typeOfId($event->id))->toBeNull();
});

it('names the type and time a DONKI id carries', function () {
    expect(DonkiEvent::typeOfId('2015-03-17T04:05:00-IPS-001'))->toBe('IPS')
        ->and(DonkiEvent::timeOfId('2015-03-17T04:05:00-IPS-001'))->toBe(strtotime('2015-03-17T04:05:00Z'))
        ->and(DonkiEvent::typeOfId('20140508-AL-002'))->toBeNull()
        ->and(DonkiEvent::timeOfId('20140508-AL-002'))->toBeNull();
});

it('orders flare classes and reads spans', function () {
    expect(DonkiEvent::flareMagnitude('C9.9'))->toBe(2.99)
        ->and(DonkiEvent::flareMagnitude('M1.0'))->toBe(3.1)
        ->and(DonkiEvent::flareMagnitude('X'))->toBe(4.1)
        ->and(DonkiEvent::flareMagnitude('Q5'))->toBeNull()
        ->and(DonkiEvent::span(2 * 86400 + 6 * 3600))->toBe('2d 6h')
        ->and(DonkiEvent::span(3 * 3600 + 20 * 60))->toBe('3h 20m')
        ->and(DonkiEvent::span(45 * 60))->toBe('45m');
});
