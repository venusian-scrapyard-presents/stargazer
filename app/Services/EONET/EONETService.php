<?php

namespace App\Services\EONET;

use App\Concerns\EONET\EONETContent;
use App\GlobalVariables;
use App\NutsAndBolts\EonetMap;
use App\NutsAndBolts\MapProjection;
use App\Services\NasaService;
use ProjectSaturnStudios\Stargazer\EONET\DataObjects\EonetCategoriesPage;
use ProjectSaturnStudios\Stargazer\EONET\DataObjects\EonetCategory;
use ProjectSaturnStudios\Stargazer\EONET\DataObjects\EonetEvent;
use ProjectSaturnStudios\Stargazer\EONET\DataObjects\EonetEventsPage;
use ProjectSaturnStudios\Stargazer\EONET\DataObjects\EonetGeometry;
use ProjectSaturnStudios\Stargazer\GIBS\DataObjects\GibsBox;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Throwable;
use Voyager\Http\Client\Response;

/**
 * EONET's natural events: dotted over NASA's Blue Marble on a canvas that re-lays itself out
 * when it is resized, and listed beside it. A dot and its row select each other.
 */
class EONETService extends NasaService
{
    use EONETContent;

    /** The map before the toolkit has laid it out. */
    protected const array MAP_FLOOR = [520, 260];

    /** The world under the dots: GIBS's Blue Marble, fetched once and kept on disk after. */
    public const string BASEMAP_LAYER = 'BlueMarble_ShadedRelief_Bathymetry';

    public const array BASEMAP_SIZE = [2048, 1024];

    /** How many events one load asks for. */
    public const int LIMIT = 150;

    /** @var list<EonetCategory> Dropdown entries after "All categories". */
    protected array $categories = [];

    /** @var list<EonetEvent> The table's rows, in order. */
    protected array $events = [];

    /** @var array<int, array{0: float, 1: float, 2: string|null}> Event index => where its dot falls, and its category id. */
    protected array $points = [];

    protected ?int $highlighted = null;

    protected ?Framebuffer $basemap = null;

    protected ?Framebuffer $framebuffer = null;

    protected ?RenderingEngine $velvet = null;

    protected ?EonetMap $layout = null;

    /** @var array{0: int, 1: int} The canvas pixels the layout was made for. */
    protected array $laid_out_for = [0, 0];

    /** Why the Blue Marble could not be had; null while it loads or once it has. */
    protected ?string $basemap_error = null;

    /** The canvas has a new frame to show. */
    protected bool $dirty = false;

    public function key(): string
    {
        return 'eonet';
    }

    public function mounted(TKColumn $root): void
    {
        $this->setMainComponents($root);
        $this->loadBasemap();
    }

    public function hasCategories(): bool
    {
        return $this->categories !== [];
    }

    public function openOnly(): bool
    {
        return $this->open->isChecked();
    }

    /**
     * The picked category; null for All categories.
     */
    public function pickedCategory(): ?EonetCategory
    {
        $selected = $this->category->selected();

        return $selected > 0 ? ($this->categories[$selected - 1] ?? null) : null;
    }

    public function isLoad(string $uuid): bool
    {
        return $uuid === $this->load?->uuid();
    }

    public function isMap(string $uuid): bool
    {
        return $uuid === $this->map?->uuid();
    }

    public function isEventsTable(string $uuid): bool
    {
        return $uuid === $this->events_table?->uuid();
    }

    /**
     * The categories landed: they fill the dropdown after All categories.
     */
    public function categoriesLoaded(GlobalVariables $bag): void
    {
        /** @var EonetCategoriesPage $page */
        $page = $bag->eonet_categories;
        $this->categories = $page->categories->values()->all();
        $this->category->setOptions(['All categories', ...array_map(fn (EonetCategory $category): string => $category->title ?? $category->id, $this->categories)]);
    }

    /**
     * The events landed: dots on the map, rows in the table, and the outcome.
     */
    public function eventsLoaded(GlobalVariables $bag, ?EonetCategory $category): void
    {
        /** @var EonetEventsPage $page */
        $page = $bag->eonet_events;
        $this->plot($page->events->values()->all());

        $in = is_null($category) ? '' : " in {$category->title}";
        $this->events === []
            ? $this->empty($bag, "No events{$in}.")
            : $this->done($bag, count($this->events)." events{$in}.");
    }

    /**
     * Every sketch tick: follow the canvas's size, and show what changed since the last one.
     */
    public function tick(): void
    {
        [$width, $height] = $this->map->pixelSize();
        if ($width < 1 || $height < 1) {
            return;
        }

        $framebuffer = $this->map->framebuffer('full');
        if ($framebuffer !== $this->framebuffer) {
            $this->framebuffer = $framebuffer;
            $this->velvet = app('drawing')->renderer('velvet', ['framebuffer' => $framebuffer]);
            $this->repaint();
        } elseif ([$width, $height] !== $this->laid_out_for) {
            $this->repaint();
        }

        if ($this->dirty) {
            $this->dirty = false;
            $this->map->present();
        }
    }

    /**
     * A click on the map, in points: the dot in reach, if any, is picked.
     */
    public function pickAt(float $x, float $y): bool
    {
        if (is_null($this->layout)) {
            return true;
        }

        $scale = $this->pixelScale();

        return $this->choose($this->layout->nearest($x * $scale, $y * $scale));
    }

    /**
     * An event picked from its dot or its row: the other follows, its dot lights up, and its
     * details show below.
     */
    public function choose(?int $index, bool $from_table = false): bool
    {
        $event = is_null($index) ? null : ($this->events[$index] ?? null);
        if (is_null($event)) {
            return true;
        }

        if (! $from_table) {
            $this->events_table->selectRow($index);
        }

        $this->highlighted = isset($this->points[$index]) ? $index : null;
        $this->repaint();

        /** @var EonetGeometry|null $latest */
        $latest = $event->geometry->last();
        $this->detail->setText(sprintf(
            '%s — %s · %s%s · %d observations · %s',
            $event->title ?? $event->id,
            $event->categories->map(fn (EonetCategory $category) => $category->title)->implode(', '),
            substr($latest?->date ?? '', 0, 10),
            is_null($latest?->magnitudeValue) ? '' : " · {$latest->magnitudeValue} {$latest->magnitudeUnit}",
            $event->geometry->count(),
            is_null($event->closed) ? 'open' : 'closed '.substr($event->closed, 0, 10),
        ));

        return true;
    }

    /**
     * @param list<EonetEvent> $events
     */
    protected function plot(array $events): void
    {
        $this->events = $events;
        $this->points = [];
        $this->highlighted = null;

        $rows = [];
        foreach ($events as $i => $event) {
            /** @var EonetGeometry|null $latest */
            $latest = $event->geometry->last();
            $category = $event->categories->first();
            $rows[] = [
                'date' => substr($latest?->date ?? '', 0, 10),
                'category' => $category?->title ?? '',
                'title' => $event->title ?? $event->id,
            ];

            $fraction = MapProjection::fraction($latest?->coordinates ?? []);
            if (! is_null($fraction)) {
                $this->points[$i] = [...$fraction, $category?->id];
            }
        }

        $this->events_table->setRows($rows);
        $this->showLegend($this->legendCategories());
        $this->detail->setText('Click a dot or a row.');
        $this->repaint();
    }

    /**
     * @return array<string, string> id => title for each category with a dot on the map, by title
     */
    protected function legendCategories(): array
    {
        $categories = [];
        foreach (array_keys($this->points) as $i) {
            $category = $this->events[$i]->categories->first();
            $categories[$category?->id ?? ''] = $category?->title ?? 'Other';
        }
        asort($categories);

        return $categories;
    }

    /**
     * @param array<string, string> $categories id => title
     */
    protected function showLegend(array $categories): void
    {
        foreach ($this->legend->children() as $child) {
            $child->remove();
        }

        foreach ($categories as $id => $title) {
            $this->legend->label('cat-'.($id === '' ? 'other' : $id), "● {$title}")->setTextColor(EonetMap::color($id === '' ? null : $id));
        }

        if (! is_null($this->basemap_error)) {
            $this->legend->label('basemap', $this->basemap_error);
        }
    }

    /**
     * The globe as it stands: the Blue Marble once it has loaded, the dots, and the pick.
     */
    protected function repaint(): void
    {
        if (is_null($this->velvet)) {
            return;
        }

        $this->laid_out_for = $this->map->pixelSize();
        $this->layout = new EonetMap($this->points, ...$this->laid_out_for, scale: $this->pixelScale());
        $layout = $this->layout;
        $this->velvet->frame(fn (RenderingEngine $g) => $layout->paint($g, $this->basemap, $this->highlighted));
        $this->dirty = true;
    }

    /**
     * The Blue Marble, from disk when an earlier run kept it, else from GIBS (then kept). Until
     * it lands the globe is plain ocean; when it cannot be had, the status bar says why.
     */
    protected function loadBasemap(): void
    {
        $path = self::basemapPath();
        if (is_file($path)) {
            try {
                $this->basemap = app('images')->decode((string) file_get_contents($path));

                return;
            } catch (Throwable) {
                // An unreadable copy is fetched again.
                unlink($path);
            }
        }

        [$width, $height] = self::BASEMAP_SIZE;
        GlobalVariables::make()->nasa->gibs()->wms()
            ->map(self::BASEMAP_LAYER, new GibsBox(-180, -90, 180, 90), $width, $height)
            ->async()
            ->then(function (Response $response) use ($path): void {
                $this->basemap = app('images')->decode($response->body());
                $this->basemap_error = null;
                if (! is_dir(dirname($path))) {
                    mkdir(dirname($path), 0775, true);
                }
                file_put_contents("{$path}.part", $response->body());
                rename("{$path}.part", $path);
                $this->repaint();
            }, function (Throwable $e): void {
                $this->basemap_error = "World map did not load ({$e->getMessage()}): plain globe.";
                $this->showLegend($this->legendCategories());
            });
    }

    public static function basemapPath(): string
    {
        [$width, $height] = self::BASEMAP_SIZE;

        return storage_path('app/maps/'.self::BASEMAP_LAYER."-{$width}x{$height}.jpg");
    }

    /** Device pixels per point of the canvas. */
    protected function pixelScale(): float
    {
        [$pixels] = $this->map->pixelSize();
        [$points] = $this->map->size() + [0];

        return $points > 0 ? $pixels / $points : 1.0;
    }

    protected function setBusy(bool $busy): void
    {
        $this->load->setEnabled(! $busy);
    }
}
