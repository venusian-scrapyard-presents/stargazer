<?php

namespace App\NutsAndBolts;

use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Fonts\GFXFont;
use Surface\NutsAndBolts\Color;

/**
 * A date range of DONKI events as swim lanes, one per event type, with the links between records
 * drawn across the lanes: a flare launches a CME, a model run predicts its arrival, a shock and a
 * storm follow. Flares are triangles sized by class, storms are bars coloured by Kp, CME analyses
 * are circles sized by speed, model runs reach forward to their predicted arrival. The layout is
 * plain numbers so it can be checked; paint() draws it.
 */
final class DonkiTimeline
{
    /** Lane colours by event type. */
    public const array COLORS = [
        'FLR' => '#ffb347',
        'CME' => '#4ea8ff',
        'CME Analysis' => '#8fc8ff',
        'GST' => '#ff5c5c',
        'IPS' => '#c084fc',
        'SEP' => '#f472b6',
        'MPC' => '#2dd4bf',
        'RBE' => '#fb7185',
        'HSS' => '#22d3ee',
        'WSA-Enlil' => '#94a3b8',
        'Notifications' => '#6b7280',
    ];

    /** Kp thresholds and the colour each storm strength draws in, NOAA's scale. */
    protected const array KP_COLORS = [[8.0, '#b91c1c'], [7.0, '#ef4444'], [6.0, '#f97316'], [5.0, '#facc15'], [0.0, '#4ade80']];

    /** Flare class rank => marker colour. */
    protected const array FLARE_COLORS = ['#9ca3af', '#d1d5db', '#fde68a', '#fb923c', '#ef4444'];

    protected const string BACKGROUND = '#17171c';

    protected const string BAND = '#1f1f26';

    protected const string GRID = '#2b2b35';

    protected const string INK = '#c9c9d1';

    /** @var list<array{type: string, y: float, height: float}> Lanes top to bottom. */
    protected array $lanes = [];

    /** @var list<array{id: string, type: string, x: float, y: float, x1: float|null, arrival: float|null, size: float, shape: string, color: string, hollow: bool}> One per event, in the events' order. */
    protected array $markers = [];

    /** @var array<string, int> Event id => marker index. */
    protected array $index = [];

    /** @var list<array{0: int, 1: int}> Marker index pairs the links join. */
    protected array $links = [];

    /** @var list<array{x: float, label: string}> Day or week ticks along the bottom. */
    protected array $ticks = [];

    protected float $gutter = 0.0;

    protected float $scale = 1.0;

    /**
     * @param list<DonkiEvent> $events
     * @param int $from Unix time the range starts.
     * @param int $to Unix time the range ends (the end of that day is the right edge).
     * @param int $width Pixels across.
     * @param int $height Pixels down.
     * @param float $scale Device pixels per point: markers and lettering grow with it.
     */
    public function __construct(
        protected array $events,
        protected int $from,
        protected int $to,
        protected int $width,
        protected int $height,
        float $scale = 1.0,
    ) {
        $this->scale = max(1.0, $scale);
        $this->layout();
    }

    /** @return list<array{type: string, y: float, height: float}> */
    public function lanes(): array
    {
        return $this->lanes;
    }

    /** @return list<array{id: string, type: string, x: float, y: float, x1: float|null, arrival: float|null, size: float, shape: string, color: string, hollow: bool}> */
    public function markers(): array
    {
        return $this->markers;
    }

    /** @return list<array{0: int, 1: int}> */
    public function links(): array
    {
        return $this->links;
    }

    /** @return list<array{x: float, label: string}> */
    public function ticks(): array
    {
        return $this->ticks;
    }

    /**
     * The event drawn nearest a pixel, within reach; null when nothing is near.
     * @return int|null The event's index in the list given.
     */
    public function nearest(float $x, float $y, ?float $reach = null): ?int
    {
        $reach ??= 14 * $this->scale;
        $best = null;
        $best_distance = $reach;
        foreach ($this->markers as $i => $marker) {
            // A bar is as near as its closest point along the lane.
            $mx = is_null($marker['x1']) ? $marker['x'] : max($marker['x'], min($marker['x1'], $x));
            $distance = hypot($x - $mx, $y - $marker['y']);
            if ($distance <= $best_distance) {
                $best = $i;
                $best_distance = $distance;
            }
        }

        return $best;
    }

    /**
     * Draw the whole timeline; the picked event, when given, is ringed and its links lit.
     */
    public function paint(RenderingEngine $g, GFXFont $font, ?string $picked = null): void
    {
        $g->clear(Color::hex(self::BACKGROUND));
        $s = $this->scale;

        foreach ($this->lanes as $i => $lane) {
            if ($i % 2 === 0) {
                $g->fillRect(0, $lane['y'] - $lane['height'] / 2, $this->width, $lane['height'], Color::hex(self::BAND));
            }
            $this->letter($g, $font, $lane['type'], 6 * $s, $lane['y'] - 4 * $s, Color::hex(self::COLORS[$lane['type']] ?? self::INK));
        }

        $bottom = $this->height - 14 * $s;
        foreach ($this->ticks as $tick) {
            $g->line($tick['x'], 0, $tick['x'], $bottom, Color::hex(self::GRID), 1.0);
            $this->letter($g, $font, $tick['label'], $tick['x'] + 2 * $s, $bottom + 3 * $s, Color::hex(self::INK));
        }
        $g->line($this->gutter, $bottom, $this->width, $bottom, Color::hex(self::GRID), 1.0);

        $picked_index = is_null($picked) ? null : ($this->index[$picked] ?? null);
        foreach ($this->links as [$a, $b]) {
            $lit = $picked_index === $a || $picked_index === $b;
            $ma = $this->markers[$a];
            $mb = $this->markers[$b];
            $g->line($ma['x'], $ma['y'], $mb['x'], $mb['y'], $lit ? Color::rgba(255, 255, 255, 0.9) : Color::rgba(200, 200, 215, 0.22), $lit ? 2.0 * $s : 1.0);
        }

        foreach ($this->markers as $i => $marker) {
            $this->shape($g, $marker);
            if ($i === $picked_index) {
                $g->strokeEllipse($marker['x'], $marker['y'], $marker['size'] + 5 * $s, $marker['size'] + 5 * $s, Color::rgba(255, 255, 255, 0.95), 2.0 * $s);
            }
        }
    }

    protected function layout(): void
    {
        $s = $this->scale;
        $this->gutter = 90 * $s;
        $bottom = $this->height - 14 * $s;

        $present = [];
        foreach ($this->events as $event) {
            $present[$event->type] = true;
        }
        $types = array_values(array_filter(array_keys(DonkiRows::TYPES), fn (string $type): bool => isset($present[$type])));
        $lane_height = $types === [] ? $bottom : $bottom / count($types);
        foreach ($types as $i => $type) {
            $this->lanes[] = ['type' => $type, 'y' => $lane_height * ($i + 0.5), 'height' => $lane_height];
        }
        $lane_y = array_column($this->lanes, 'y', 'type');

        $span = max(1, $this->to + 86400 - $this->from);
        $plot = max(1.0, $this->width - $this->gutter - 8 * $s);
        $x = fn (int $time): float => $this->gutter + ($time - $this->from) / $span * $plot;

        foreach ($this->events as $i => $event) {
            $this->index[$event->id] ??= $i;
            [$shape, $size, $color, $hollow] = $this->look($event, $lane_height);
            $this->markers[] = [
                'id' => $event->id,
                'type' => $event->type,
                'x' => $x($event->time),
                'y' => $lane_y[$event->type],
                'x1' => is_null($event->end) ? null : $x($event->end),
                'arrival' => is_null($event->arrival) ? null : $x($event->arrival),
                'size' => $size,
                'shape' => $shape,
                'color' => $color,
                'hollow' => $hollow,
            ];
        }

        foreach ($this->events as $i => $event) {
            foreach ($event->links as $link) {
                $j = $this->index[$link] ?? null;
                if (! is_null($j) && $j !== $i) {
                    $pair = $i < $j ? [$i, $j] : [$j, $i];
                    $this->links[$pair[0].':'.$pair[1]] = $pair;
                }
            }
        }
        $this->links = array_values($this->links);

        // A tick a day up to six weeks, a week beyond; the label is the day it starts.
        $days = intdiv($span, 86400);
        $step = $days <= 42 ? 86400 : 7 * 86400;
        $tick_width = $step / $span * $plot;
        $every = max(1, (int) ceil(34 * $s / max(1e-6, $tick_width)));
        for ($n = 0, $time = $this->from; $time < $this->to + 86400; $time += $step, $n++) {
            if ($n % $every === 0) {
                $this->ticks[] = ['x' => $x($time), 'label' => gmdate('m-d', $time)];
            }
        }
    }

    /**
     * @return array{0: string, 1: float, 2: string, 3: bool} shape, size in pixels, colour, hollow
     */
    protected function look(DonkiEvent $event, float $lane_height): array
    {
        $s = $this->scale;
        $cap = max(3.0 * $s, $lane_height / 2 - 2 * $s);

        return match ($event->type) {
            'FLR' => ['triangle', min($cap, (3 + ($event->magnitude ?? 1) * 1.6) * $s), self::FLARE_COLORS[(int) floor($event->magnitude ?? 0)] ?? self::FLARE_COLORS[0], false],
            'GST' => ['bar', min($cap, max(3.0 * $s, ($event->magnitude ?? 4) / 9 * $cap)), self::kpColor($event->magnitude ?? 4.0), false],
            'CME' => ['circle', 4 * $s, self::COLORS['CME'], false],
            'CME Analysis' => ['circle', min($cap, (3 + ($event->magnitude ?? 400) / 400) * $s), self::COLORS['CME Analysis'], ! $event->firm],
            'WSA-Enlil' => ['diamond', 4 * $s, self::COLORS['WSA-Enlil'], false],
            'Notifications' => ['dot', 2 * $s, self::COLORS['Notifications'], false],
            default => ['square', 3.5 * $s, self::COLORS[$event->type] ?? self::INK, false],
        };
    }

    protected function shape(RenderingEngine $g, array $m): void
    {
        $s = $this->scale;
        $color = Color::hex($m['color']);
        [$x, $y, $r] = [$m['x'], $m['y'], $m['size']];

        match ($m['shape']) {
            'triangle' => $g->fillTriangle($x, $y - $r, $x - $r, $y + $r * 0.8, $x + $r, $y + $r * 0.8, $color),
            'bar' => $g->fillRect($x, $y - $r / 2, max(2 * $s, ($m['x1'] ?? $x + 2 * $s) - $x), $r, $color),
            'circle' => $m['hollow'] ? $g->strokeEllipse($x, $y, $r, $r, $color, 1.5 * $s) : $g->fillEllipse($x, $y, $r, $r, $color),
            'diamond' => $g->fillPolygon([[$x, $y - $r], [$x + $r, $y], [$x, $y + $r], [$x - $r, $y]], $color),
            'dot' => $g->fillEllipse($x, $y, $r, $r, $color),
            default => $g->fillRect($x - $r, $y - $r, 2 * $r, 2 * $r, $color),
        };

        // A model run reaches forward to the arrival it predicts: a faint line and a tick there.
        if (! is_null($m['arrival'])) {
            $g->line($x, $y, $m['arrival'], $y, Color::rgba(148, 163, 184, 0.6), 1.0);
            $g->line($m['arrival'], $y - $r, $m['arrival'], $y + $r, $color, 1.5 * $s);
        }
    }

    /** Lettering in the classic 5x7 face, scaled with the display so it reads on HiDPI. */
    protected function letter(RenderingEngine $g, GFXFont $font, string $text, float $x, float $y, Color $color): void
    {
        $g->push()->translate($x, $y)->scale($this->scale);
        $g->text($text, 0, 0, $color, $font);
        $g->pop();
    }

    protected static function kpColor(float $kp): string
    {
        foreach (self::KP_COLORS as [$at, $color]) {
            if ($kp >= $at) {
                return $color;
            }
        }

        return self::KP_COLORS[array_key_last(self::KP_COLORS)][1];
    }
}
