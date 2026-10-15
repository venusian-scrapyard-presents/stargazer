<?php

namespace App\NutsAndBolts;

use Closure;
use ProjectSaturnStudios\Stargazer\GIBS\Vector\GibsGeometryType;
use ProjectSaturnStudios\Stargazer\GIBS\Vector\GibsStyleColor;
use ProjectSaturnStudios\Stargazer\GIBS\Vector\GibsStyleLayer;
use ProjectSaturnStudios\Stargazer\GIBS\Vector\GibsVectorFeature;
use ProjectSaturnStudios\Stargazer\GIBS\Vector\GibsVectorStyle;
use ProjectSaturnStudios\Stargazer\GIBS\Vector\GibsVectorTile;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\Region;
use Surface\NutsAndBolts\Color;

/**
 * A GIBS vector overlay drawn as GIBS's own style says: a tile's features evaluated once into a
 * draw list, then placed and drawn with VelvetGE whenever the tile is in view. Circles, lines and
 * fills; symbol layers are text, which these draw lists leave out.
 */
final class GibsVectors
{
    /**
     * A tile's features as its style draws them at $zoom, layer by layer: circles packed 24 bytes
     * each, lines and fills one entry per feature. Positions are tile fractions (0..1), sizes style
     * pixels, colours 0xRRGGBBAA; placing and scaling happen at draw time, so panning reuses it.
     *
     * @return array{0: list<array<int, mixed>>, 1: int} The draw list, and how many features it styled.
     */
    public static function drawList(GibsVectorTile $vector, GibsVectorStyle $style, int $zoom): array
    {
        $list = [];
        $features = 0;
        $colour = function (GibsStyleLayer $layer, GibsVectorFeature $feature, string $property, string $opacity) use ($zoom): ?int {
            $rgba = GibsStyleColor::parse($layer->paint($property, $feature, $zoom));
            if (is_null($rgba)) {
                return null;
            }
            $alpha = max(0.0, min(1.0, $rgba[3] * (float) $layer->paint($opacity, $feature, $zoom, 1.0)));

            return ((int) round($rgba[0]) << 24) | ((int) round($rgba[1]) << 16) | ((int) round($rgba[2]) << 8) | (int) round($alpha * 255);
        };

        foreach ($vector->layers as $source) {
            $unit = 1 / $source->extent;
            $fractions = fn (array $points): string => pack('g*', ...array_merge(...array_map(fn (array $p): array => [$p[0] * $unit, $p[1] * $unit], $points)));
            foreach ($style->layersFor($source->name) as $layer) {
                $circles = '';
                foreach ($source->features as $feature) {
                    if (! in_array($layer->type, ['circle', 'line', 'fill'], true) || ! $layer->draws($feature, $zoom)) {
                        continue;
                    }
                    $features++;
                    if ($layer->type === 'circle') {
                        $fill = $colour($layer, $feature, 'circle-color', 'circle-opacity') ?? 0x000000FF;
                        $radius = (float) $layer->paint('circle-radius', $feature, $zoom, 5);
                        $stroke = (float) $layer->paint('circle-stroke-width', $feature, $zoom, 0);
                        $edge = $stroke > 0 ? ($colour($layer, $feature, 'circle-stroke-color', 'circle-stroke-opacity') ?? 0) : 0;
                        foreach ($feature->parts() as $part) {
                            $circles .= pack('g4N2', $part[0][0] * $unit, $part[0][1] * $unit, $radius, $stroke, $fill, $edge);
                        }
                    } elseif ($layer->type === 'line') {
                        $line = $colour($layer, $feature, 'line-color', 'line-opacity') ?? 0x000000FF;
                        $width = (float) $layer->paint('line-width', $feature, $zoom, 1);
                        foreach ($feature->parts() as $part) {
                            $list[] = ['line', $line, $width, $fractions($part), $feature->type === GibsGeometryType::POLYGON];
                        }
                    } elseif ($feature->type === GibsGeometryType::POLYGON) {
                        $fill = $colour($layer, $feature, 'fill-color', 'fill-opacity') ?? 0x000000FF;
                        $outline = array_key_exists('fill-outline-color', $layer->paint) ? $colour($layer, $feature, 'fill-outline-color', 'fill-opacity') : null;
                        foreach ($feature->polygons() as $rings) {
                            $list[] = ['fill', $fill, $outline, array_map($fractions, $rings)];
                        }
                    }
                }
                if ($circles !== '') {
                    $list[] = ['circles', $circles];
                }
            }
        }

        return [$list, $features];
    }

    /**
     * One tile's draw list on the canvas: the tile's square at ($left, $top) $side pixels across,
     * clipped to that square (tiles carry a margin of their neighbours' features) and to $within.
     * $scale turns style pixels into canvas pixels.
     *
     * @param list<array<int, mixed>> $list
     */
    public static function draw(RenderingEngine $g, array $list, float $left, float $top, float $side, float $scale, ?Region $within = null): void
    {
        $square = new Region((int) floor($left), (int) floor($top), (int) ceil($side) + 1, (int) ceil($side) + 1);
        $clip = $square->intersect($within ?? Region::wholeSurface($g->width(), $g->height()));
        if (is_null($clip)) {
            return;
        }

        $colour = fn (int $rgba): Color => Color::rgba(($rgba >> 24) & 0xFF, ($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, ($rgba & 0xFF) / 255);
        $place = function (string $packed) use ($left, $top, $side): array {
            $values = array_values(unpack('g*', $packed));
            $points = [];
            for ($i = 0, $n = count($values); $i < $n; $i += 2) {
                $points[] = [$left + $values[$i] * $side, $top + $values[$i + 1] * $side];
            }

            return $points;
        };

        $g->push()->clip($clip);
        foreach ($list as $op) {
            match ($op[0]) {
                'circles' => self::circles($g, $op[1], $left, $top, $side, $scale, $colour),
                'line' => $g->polyline($place($op[3]), $colour($op[1]), max(0.5, $op[2] * $scale), $op[4]),
                'fill' => self::fill($g, array_map($place, $op[3]), $colour($op[1]), is_null($op[2]) ? null : $colour($op[2])),
            };
        }
        $g->pop();
    }

    protected static function circles(RenderingEngine $g, string $circles, float $left, float $top, float $side, float $scale, Closure $colour): void
    {
        for ($at = 0, $n = strlen($circles); $at < $n; $at += 24) {
            ['x' => $x, 'y' => $y, 'r' => $r, 's' => $s, 'fill' => $fill, 'edge' => $edge] = unpack('gx/gy/gr/gs/Nfill/Nedge', $circles, $at);
            $cx = $left + $x * $side;
            $cy = $top + $y * $side;
            $g->fillEllipse($cx, $cy, $r * $scale, $r * $scale, $colour($fill));
            if ($s > 0) {
                $g->strokeEllipse($cx, $cy, $r * $scale, $r * $scale, $colour($edge), $s * $scale);
            }
        }
    }

    /** @param list<list<array{float, float}>> $contours */
    protected static function fill(RenderingEngine $g, array $contours, Color $fill, ?Color $outline): void
    {
        $g->fillPath($contours, $fill);
        foreach (is_null($outline) ? [] : $contours as $contour) {
            $g->polyline($contour, $outline, 1.0, true);
        }
    }
}
