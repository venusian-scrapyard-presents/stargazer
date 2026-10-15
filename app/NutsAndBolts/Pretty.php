<?php

namespace App\NutsAndBolts;

use BackedEnum;
use Traversable;
use UnitEnum;

/**
 * Stargazer DTOs as indented JSON for the detail text areas.
 */
final class Pretty
{
    public static function json(mixed $value): string
    {
        return json_encode(self::plain($value), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
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
