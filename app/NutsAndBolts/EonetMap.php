<?php

namespace App\NutsAndBolts;

use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\Filter;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\NutsAndBolts\Color;

/**
 * EONET's events over the world, laid out on a canvas's pixels: the largest 2:1 globe that fits,
 * NASA's Blue Marble across it, the equator, tropics and prime meridian over that, and a dot per
 * event in its category's colour.
 */
final class EonetMap
{
    /** Dot colours by EONET category id. */
    public const array COLORS = [
        'wildfires' => '#ff5a1f',
        'volcanoes' => '#e11d48',
        'severeStorms' => '#a78bfa',
        'floods' => '#38bdf8',
        'earthquakes' => '#f59e0b',
        'landslides' => '#a16207',
        'drought' => '#facc15',
        'dustHaze' => '#d6b98c',
        'seaLakeIce' => '#e0f2fe',
        'snow' => '#ffffff',
        'tempExtremes' => '#f472b6',
        'waterColor' => '#2dd4bf',
        'manmade' => '#94a3b8',
    ];

    public const string OTHER = '#c9c9d1';

    protected const string BACKGROUND = '#0b1622';

    /** The globe before the Blue Marble has loaded, or when it did not. */
    protected const string OCEAN = '#1b4f7a';

    protected const string PICKED = '#ffb000';

    /** Equator and tropics, as latitudes. */
    protected const array PARALLELS = [0.0, 23.44, -23.44];

    /** A dot's radius, and how far from one a click still picks it, in points. */
    protected const float DOT = 5.0;

    protected const float REACH = 10.0;

    /**
     * @param array<int, array{0: float, 1: float, 2: string|null}> $points Event index => [across, down, category id], across and down from 0 to 1.
     * @param int $width canvas pixels
     * @param int $height canvas pixels
     * @param float $scale device pixels per point
     */
    public function __construct(
        protected array $points,
        protected int $width,
        protected int $height,
        protected float $scale = 1.0,
    ) {}

    /**
     * The largest 2:1 box (an equirectangular globe) centred on the canvas.
     * @return array{0: float, 1: float, 2: float, 3: float} x, y, width, height
     */
    public function globe(): array
    {
        $width = (float) min($this->width, 2 * $this->height);
        $height = $width / 2;

        return [($this->width - $width) / 2, ($this->height - $height) / 2, $width, $height];
    }

    /**
     * @return array{0: float, 1: float}|null an event's dot centre, in canvas pixels
     */
    public function position(int $index): ?array
    {
        if (! isset($this->points[$index])) {
            return null;
        }

        [$x, $y, $width, $height] = $this->globe();
        [$across, $down] = $this->points[$index];

        return [$x + $across * $width, $y + $down * $height];
    }

    /**
     * The event whose dot is nearest a click, in canvas pixels; null when none is in reach.
     * Later dots paint over earlier ones, so a tie goes to the one on top.
     */
    public function nearest(float $x, float $y): ?int
    {
        $reach = self::REACH * $this->scale;
        $best = null;
        $best_distance = $reach * $reach;

        foreach (array_keys($this->points) as $index) {
            [$px, $py] = $this->position($index);
            $distance = ($px - $x) ** 2 + ($py - $y) ** 2;
            if ($distance <= $best_distance) {
                $best = $index;
                $best_distance = $distance;
            }
        }

        return $best;
    }

    public function paint(RenderingEngine $g, ?Framebuffer $basemap, ?int $picked): void
    {
        $g->clear(Color::hex(self::BACKGROUND));
        [$x, $y, $width, $height] = $this->globe();

        if (is_null($basemap)) {
            $g->fillRect($x, $y, $width, $height, Color::hex(self::OCEAN));
        } else {
            $g->image($basemap, $x, $y, $width, $height, filter: Filter::LINEAR);
        }

        $grid = Color::rgba(255, 255, 255, 0.25);
        foreach (self::PARALLELS as $latitude) {
            $line = $y + (90.0 - $latitude) / 180.0 * $height;
            $g->line($x, $line, $x + $width, $line, $grid, $this->scale);
        }
        $g->line($x + $width / 2, $y, $x + $width / 2, $y + $height, $grid, $this->scale);

        $radius = self::DOT * $this->scale;
        $edge = Color::rgba(0, 0, 0, 0.75);
        foreach ($this->points as $index => [, , $category]) {
            [$px, $py] = $this->position($index);
            $g->fillEllipse($px, $py, $radius + $this->scale, $radius + $this->scale, $edge);
            $g->fillEllipse($px, $py, $radius, $radius, self::color($category));
        }

        // The picked event's ring goes on last, over any dot that shares its spot.
        if (! is_null($picked) && ! is_null($at = $this->position($picked))) {
            $g->strokeEllipse($at[0], $at[1], $radius + 4 * $this->scale, $radius + 4 * $this->scale, Color::hex(self::PICKED), 2 * $this->scale);
        }
    }

    public static function color(?string $category): Color
    {
        return Color::hex(self::COLORS[$category] ?? self::OTHER);
    }
}
