<?php

namespace App\NutsAndBolts;

use BackedEnum;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\Cme;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\CmeAnalysis;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\Flare;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\GeomagneticStorm;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\HighSpeedStream;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\InterplanetaryShock;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\MagnetopauseCrossing;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\Notification;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\RadiationBeltEnhancement;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\SolarEnergeticParticle;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\WsaEnlilSimulation;
use Traversable;
use UnitEnum;

/**
 * DONKI records as donki_records keeps them, and which days of a type still need asking for.
 * A record is stored as its DTO's properties, whose names are DONKI's own keys, so fromArray()
 * rebuilds it. Recent days are still being revised upstream: a day counts as settled once it
 * was fetched SETTLED_AFTER after it ended, and an unsettled fetch is reused for FRESH_FOR.
 */
final class DonkiRecords
{
    /** Event type => the DTO class that type hydrates to. */
    public const array CLASSES = [
        'CME' => Cme::class,
        'CME Analysis' => CmeAnalysis::class,
        'GST' => GeomagneticStorm::class,
        'IPS' => InterplanetaryShock::class,
        'FLR' => Flare::class,
        'SEP' => SolarEnergeticParticle::class,
        'MPC' => MagnetopauseCrossing::class,
        'RBE' => RadiationBeltEnhancement::class,
        'HSS' => HighSpeedStream::class,
        'WSA-Enlil' => WsaEnlilSimulation::class,
        'Notifications' => Notification::class,
    ];

    /** How long after a day ends a fetch of it is trusted for good. */
    public const int SETTLED_AFTER = 7 * 86400;

    /** How long a fetch of a day not yet settled is reused. */
    public const int FRESH_FOR = 3600;

    public static function encode(object $record): string
    {
        return json_encode(self::plain($record), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $type A CLASSES key.
     */
    public static function decode(string $type, string $json): object
    {
        return self::CLASSES[$type]::fromArray(json_decode($json, true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * What makes a record one row: its DONKI id, or for a CME analysis (several per CME) its link.
     * @param string $type A CLASSES key.
     */
    public static function key(string $type, object $record): string
    {
        $id = DonkiRows::row($type, $record)['id'];

        return $record instanceof CmeAnalysis
            ? ($record->link ?? $id.'@'.($record->time21_5 ?? ''))
            : $id;
    }

    /**
     * The span of days a type still needs fetched, first to last; null when every day is held.
     * @param array<string, int> $fetched Day (Y-m-d) => unix time it was last fetched.
     * @param string $from First day, Y-m-d.
     * @param string $to Last day, Y-m-d.
     * @return array{0: string, 1: string}|null
     */
    public static function missingSpan(array $fetched, string $from, string $to, int $now): ?array
    {
        $missing = [];
        foreach (self::days($from, $to) as $day) {
            $at = $fetched[$day] ?? null;
            $ended = strtotime($day.'T00:00:00Z') + 86400;
            $settled = ! is_null($at) && $at >= $ended + self::SETTLED_AFTER;
            $fresh = ! is_null($at) && $now - $at < self::FRESH_FOR;
            if (! $settled && ! $fresh) {
                $missing[] = $day;
            }
        }

        return $missing === [] ? null : [$missing[0], $missing[count($missing) - 1]];
    }

    /**
     * Every day from $from to $to, both included.
     * @return list<string>
     */
    public static function days(string $from, string $to): array
    {
        $days = [];
        for ($at = strtotime($from.'T00:00:00Z'), $end = strtotime($to.'T00:00:00Z'); $at <= $end; $at += 86400) {
            $days[] = gmdate('Y-m-d', $at);
        }

        return $days;
    }

    protected static function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof Traversable => array_map(self::plain(...), iterator_to_array($value)),
            is_array($value) => array_map(self::plain(...), $value),
            is_object($value) => array_map(self::plain(...), get_object_vars($value)),
            default => $value,
        };
    }
}
