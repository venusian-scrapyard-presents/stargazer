<?php

namespace App\NutsAndBolts;

use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\CmeAnalysis;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\CmeInput;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\Flare;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\GeomagneticStorm;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\KpIndex;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\LinkedEvent;
use ProjectSaturnStudios\Stargazer\DONKI\DataObjects\WsaEnlilSimulation;
use Voyager\NutsAndBolts\Collection;

/**
 * One DONKI record as the timeline and the chain see it: when it happened, how long it lasted,
 * how big it was, and which other records it is linked to. DONKI ids carry the event's type and
 * time ("2017-09-06T08:57:00-FLR-001"), so a linked record never loaded still has both.
 */
final readonly class DonkiEvent
{
    /** Flare classes, faintest first; a class letter plus its number gives the magnitude. */
    public const array FLARE_CLASSES = ['A' => 0, 'B' => 1, 'C' => 2, 'M' => 3, 'X' => 4];

    /**
     * @param string $id The DONKI activity id (a CME analysis carries its CME's).
     * @param string $type A DonkiRows::TYPES key.
     * @param int $time Unix time.
     * @param int|null $end Unix time the event ran until, when it has a span (storm, flare).
     * @param int|null $arrival Unix time a model predicts a shock reaching Earth (WSA-Enlil).
     * @param float|null $magnitude Flare class as 0..4 plus a fraction, storm peak Kp, CME speed in km/s.
     * @param bool $firm Whether the magnitude is the record's best (a CME analysis not marked most accurate is not).
     * @param string $label The one detail that matters, as the table shows it.
     * @param list<string> $links Ids of the records this one is linked to.
     * @param object $record The DTO itself.
     */
    public function __construct(
        public string $id,
        public string $type,
        public int $time,
        public ?int $end,
        public ?int $arrival,
        public ?float $magnitude,
        public bool $firm,
        public string $label,
        public array $links,
        public object $record,
    ) {}

    /**
     * @param string $type A DonkiRows::TYPES key.
     * @param object $record One of that type's DTOs.
     */
    public static function from(string $type, object $record): self
    {
        $row = DonkiRows::row($type, $record);
        $time = self::stamp($row['time']) ?? 0;

        $end = match (true) {
            $record instanceof Flare => self::stamp($record->endTime),
            $record instanceof GeomagneticStorm => $record->allKpIndex->map(fn (KpIndex $kp) => self::stamp($kp->observedTime))->filter()->max(),
            default => null,
        };
        $magnitude = match (true) {
            $record instanceof Flare => self::flareMagnitude($record->classType),
            $record instanceof GeomagneticStorm => $record->allKpIndex->map(fn (KpIndex $kp) => $kp->kpIndex)->filter(fn ($kp) => ! is_null($kp))->max(),
            $record instanceof CmeAnalysis => $record->speed,
            default => null,
        };
        $links = match (true) {
            $record instanceof CmeAnalysis => array_values(array_filter([$record->associatedCMEID])),
            $record instanceof WsaEnlilSimulation => $record->cmeInputs->map(fn (CmeInput $input) => $input->cmeid)->filter()->values()->all(),
            isset($record->linkedEvents) && $record->linkedEvents instanceof Collection => $record->linkedEvents->map(fn (LinkedEvent $link) => $link->activityID)->filter()->values()->all(),
            default => [],
        };

        return new self(
            id: $row['id'],
            type: $type,
            time: $time,
            end: is_int($end) && $end > $time ? $end : null,
            arrival: $record instanceof WsaEnlilSimulation ? self::stamp($record->estimatedShockArrivalTime) : null,
            magnitude: is_null($magnitude) ? null : (float) $magnitude,
            firm: ! ($record instanceof CmeAnalysis) || $record->isMostAccurate !== false,
            label: $row['detail'],
            links: array_values(array_unique(array_filter($links, fn (string $id): bool => $id !== $row['id']))),
            record: $record,
        );
    }

    /**
     * The type a DONKI id names ("…-FLR-001" is a flare), as a DonkiRows::TYPES key; null for an
     * id of another shape (a notification's message id).
     */
    public static function typeOfId(string $id): ?string
    {
        return preg_match('/-([A-Z]+)-\d+$/', $id, $match) && isset(DonkiRows::TYPES[$match[1]]) ? $match[1] : null;
    }

    /**
     * The time a DONKI id names, as unix time; null for an id of another shape.
     */
    public static function timeOfId(string $id): ?int
    {
        return preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})-[A-Z]+-\d+$/', $id, $match) ? self::stamp($match[1].'Z') : null;
    }

    /**
     * A DONKI time ("2017-09-06T08:57Z") as unix time; null when there is none.
     */
    public static function stamp(?string $time): ?int
    {
        if (is_null($time) || trim($time) === '') {
            return null;
        }

        $stamp = strtotime($time);

        return $stamp === false ? null : $stamp;
    }

    /**
     * A flare class ("M2.2") as a number that orders classes: the class's rank plus the tenth
     * of its number, so C9.9 is 2.99 and M1.0 is 3.1. null for a class not understood.
     */
    public static function flareMagnitude(?string $class): ?float
    {
        if (is_null($class) || ! preg_match('/^([ABCMX])(\d+(?:\.\d+)?)?$/i', trim($class), $match)) {
            return null;
        }

        $rank = self::FLARE_CLASSES[strtoupper($match[1])];
        $number = isset($match[2]) ? min((float) $match[2], 9.9) : 1.0;

        return $rank + $number / 10;
    }

    /**
     * How long a span reads: "2d 6h", "45m", "3h".
     */
    public static function span(int $seconds): string
    {
        $seconds = abs($seconds);
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return match (true) {
            $days > 0 => "{$days}d {$hours}h",
            $hours > 0 => "{$hours}h".($minutes > 0 ? " {$minutes}m" : ''),
            default => "{$minutes}m",
        };
    }
}
