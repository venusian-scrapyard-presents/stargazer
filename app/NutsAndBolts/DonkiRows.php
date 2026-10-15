<?php

namespace App\NutsAndBolts;

use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\CmeAnalysis;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\Flare;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\GeomagneticStorm;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\Instrument;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\InterplanetaryShock;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\KpIndex;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\Notification;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\WsaEnlilSimulation;
use Voyager\NutsAndBolts\Collection;

/**
 * DONKI's eleven event shapes as one table: type, id, time, and the one detail that matters
 * for that type.
 */
final class DonkiRows
{
    /** Event type => the DonkiAPIService method that lists it. */
    public const array TYPES = [
        'CME' => 'cme',
        'CME Analysis' => 'cmeAnalysis',
        'GST' => 'gst',
        'IPS' => 'ips',
        'FLR' => 'flr',
        'SEP' => 'sep',
        'MPC' => 'mpc',
        'RBE' => 'rbe',
        'HSS' => 'hss',
        'WSA-Enlil' => 'wsaEnlilSimulations',
        'Notifications' => 'notifications',
    ];

    protected const array IDS = ['activityID', 'flrID', 'gstID', 'hssID', 'mpcID', 'rbeID', 'sepID', 'messageID', 'simulationID', 'associatedCMEID'];

    /** The time a record is listed and filtered by; a CME analysis goes by its CME's start, as DONKI's own query does. */
    protected const array TIMES = ['startTime', 'beginTime', 'eventTime', 'messageIssueTime', 'modelCompletionTime', 'associatedCMEstartTime', 'time'];

    /**
     * @param string $type A TYPES key.
     * @param object $event One of that type's DTOs.
     * @return array{type: string, id: string, time: string, detail: string}
     */
    public static function row(string $type, object $event): array
    {
        return [
            'type' => $type,
            'id' => self::first($event, self::IDS),
            'time' => self::first($event, self::TIMES),
            'detail' => self::detail($event),
        ];
    }

    protected static function detail(object $event): string
    {
        return match (true) {
            $event instanceof Flare => $event->classType ?? '',
            $event instanceof GeomagneticStorm => 'Kp '.($event->allKpIndex->map(fn (KpIndex $kp) => $kp->kpIndex)->filter(fn ($kp) => ! is_null($kp))->max() ?? '?'),
            $event instanceof CmeAnalysis => trim(sprintf('%s km/s, type %s', $event->speed ?? '?', $event->type ?? '?')),
            $event instanceof InterplanetaryShock => $event->location ?? '',
            $event instanceof Notification => $event->messageType ?? '',
            $event instanceof WsaEnlilSimulation => is_null($event->estimatedShockArrivalTime) ? 'no shock arrival at Earth' : "shock arrives {$event->estimatedShockArrivalTime}",
            isset($event->sourceLocation) => $event->sourceLocation,
            isset($event->instruments) && $event->instruments instanceof Collection => $event->instruments->map(fn (Instrument $instrument) => $instrument->displayName)->implode(', '),
            default => '',
        };
    }

    /**
     * @param object $event
     * @param list<string> $properties
     * @return string
     */
    protected static function first(object $event, array $properties): string
    {
        foreach ($properties as $property) {
            if (isset($event->{$property}) && is_scalar($event->{$property})) {
                return (string) $event->{$property};
            }
        }

        return '';
    }
}
