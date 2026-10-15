<?php

namespace App\NutsAndBolts;

/**
 * GeoJSON coordinates onto an equirectangular map: longitude across, latitude down.
 */
final class MapProjection
{
    /**
     * Where a geometry falls across and down an equirectangular map, each from 0 to 1.
     * @param array $coordinates A Point's [lon, lat], or a Polygon's rings (the outer ring's centroid is used).
     * @return array{0: float, 1: float}|null null for an empty geometry.
     */
    public static function fraction(array $coordinates): ?array
    {
        $centre = self::centre($coordinates);
        if (is_null($centre)) {
            return null;
        }

        [$lon, $lat] = $centre;

        return [
            (max(-180.0, min(180.0, $lon)) + 180.0) / 360.0,
            (90.0 - max(-90.0, min(90.0, $lat))) / 180.0,
        ];
    }

    /**
     * @param array $coordinates
     * @return array{0: float, 1: float}|null
     */
    protected static function centre(array $coordinates): ?array
    {
        if ($coordinates === []) {
            return null;
        }

        // A position: [lon, lat].
        if (is_numeric($coordinates[0])) {
            return [(float) $coordinates[0], (float) ($coordinates[1] ?? 0)];
        }

        // A ring of positions: average them, without the closing repeat of the first. Longitudes
        // are taken the short way round from the first vertex, so a ring across the antimeridian
        // (179, -179) centres on 180, not on Greenwich.
        if (is_array($coordinates[0]) && isset($coordinates[0][0]) && is_numeric($coordinates[0][0])) {
            $ring = $coordinates;
            if (count($ring) > 1 && $ring[0] == end($ring)) {
                array_pop($ring);
            }

            $first = (float) $ring[0][0];
            $longitudes = array_map(fn (array $position): float => $first + fmod(fmod((float) $position[0] - $first + 540.0, 360.0) + 360.0, 360.0) - 180.0, $ring);
            $longitude = array_sum($longitudes) / count($ring);

            return [
                // back into [-180, 180], keeping 180 itself (the antimeridian) as 180
                $longitude > 180.0 ? $longitude - 360.0 : ($longitude < -180.0 ? $longitude + 360.0 : $longitude),
                array_sum(array_column($ring, 1)) / count($ring),
            ];
        }

        // Rings (a Polygon) or polygons (a MultiPolygon): the first one's outer ring.
        return self::centre($coordinates[0]);
    }
}
