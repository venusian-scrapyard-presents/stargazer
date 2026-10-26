<?php

namespace App\Services\GIBS;

use App\Concerns\GIBS\GIBSContent;
use App\GlobalVariables;
use App\NutsAndBolts\PictureViewer;
use App\NutsAndBolts\Primary;
use App\NutsAndBolts\GibsVectors;
use App\Services\NasaService;
use App\Workflows\GIBS\LoadGIBSView\LoadGIBSCapabilitiesStart;
use App\Workflows\GIBS\LoadGIBSView\LoadGIBSTilesStart;
use DateTimeImmutable;
use ProjectSaturnStudios\Stargazer\GIBS\DataObjects\GibsBox;
use ProjectSaturnStudios\Stargazer\GIBS\Enums\GibsProjection;
use ProjectSaturnStudios\Stargazer\GIBS\Metadata\GibsColorMaps;
use ProjectSaturnStudios\Stargazer\GIBS\Vector\GibsVectorStyle;
use ProjectSaturnStudios\Stargazer\GIBS\Vector\GibsVectorTile;
use ProjectSaturnStudios\Stargazer\GIBS\Wmts\DataObjects\WmtsCapabilities;
use ProjectSaturnStudios\Stargazer\GIBS\Wmts\DataObjects\WmtsLayer;
use ProjectSaturnStudios\Stargazer\GIBS\Wmts\DataObjects\WmtsTileMatrix;
use ProjectSaturnStudios\Stargazer\NasaClient;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Windows\Mail\View\CanvasDragged;
use Surface\Contracts\Windows\Mail\View\CanvasPressed;
use Surface\Contracts\Windows\Mail\View\CanvasReleased;
use Surface\Contracts\Windows\Mail\View\CanvasWheeled;
use Surface\Contracts\Windows\Mail\View\PrimitiveMail;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\NutsAndBolts\Color;
use Voyager\Http\Client\Response;
use Voyager\Workflows\Flow;

/**
 * NASA's Global Imagery Browse Services on a canvas. The WMTS capabilities say which layers there
 * are and how they are tiled; the tiles covering the view load six at a time, each decoded by
 * app('images') (in C when ext-imgdec is loaded) and drawn by VelvetGE the moment it lands. Zoom
 * and pan step through the tile pyramid, so every tile pixel is one canvas pixel. A colour-mapped
 * layer gets its legend drawn over the map's corner. A vector overlay (fires, borders, dams…) is
 * drawn feature by feature as GIBS's own style says. A left-button drag moves the map, the wheel
 * steps the zoom, and a trackpad scroll moves the map, or zooms with ⌘/Ctrl held.
 */
class GIBSService extends NasaService
{
    use GIBSContent;

    /** Layers worth a look, in menu order; a projection lists those it has. */
    protected const array LAYERS = [
        'MODIS_Terra_CorrectedReflectance_TrueColor' => 'MODIS Terra · true colour',
        'MODIS_Aqua_CorrectedReflectance_TrueColor' => 'MODIS Aqua · true colour',
        'VIIRS_SNPP_CorrectedReflectance_TrueColor' => 'VIIRS SNPP · true colour',
        'VIIRS_NOAA20_CorrectedReflectance_TrueColor' => 'VIIRS NOAA-20 · true colour',
        'MODIS_Terra_Land_Surface_Temp_Day' => 'Land surface temperature, day',
        'MERRA2_2m_Air_Temperature_Monthly' => 'Air temperature, monthly',
        'BlueMarble_ShadedRelief_Bathymetry' => 'Blue Marble',
    ];

    /** Vector layers to lay over the imagery, by menu label; a projection lists those it has. */
    protected const array OVERLAYS = [
        'No overlay' => null,
        'Fires (VIIRS NOAA-20)' => 'VIIRS_NOAA20_Thermal_Anomalies_375m_All',
        'International borders' => 'DoS_International_Boundaries',
        'Reservoirs' => 'GRanD_Reservoirs',
        'Dams' => 'GRanD_Dams',
        'Settlements' => 'GRUMP_Settlements',
    ];

    protected const array PROJECTIONS = [
        'Geographic' => GibsProjection::EPSG4326,
        'Arctic' => GibsProjection::EPSG3413,
        'Antarctic' => GibsProjection::EPSG3031,
    ];

    protected const string BACKGROUND = '#0b1016';

    /** Decoded tiles kept for panning back and forth: a 512² tile is 1 MB, and the Pi runs PHP at 128 MB. */
    protected const int CACHE = 24;

    /** Vector draw lists kept. */
    protected const int VECTOR_CACHE = 24;

    /** @var list<string|null> The overlay identifiers the menu lists, in its order. */
    protected array $overlays = [null];

    /** @var array<string, list<array<int, mixed>>> Vector draw lists by tileKey() (which carries the view zoom). */
    protected array $vectors = [];

    /** @var array<string, GibsVectorStyle|false> Styles by id; false while one loads or when it would not. */
    protected array $styles = [];

    /** @var array<string, WmtsCapabilities> By projection value. */
    protected array $capabilities = [];

    /** @var list<string> The layer identifiers the menu lists, in its order. */
    protected array $listed = [];

    /** The projection $listed was made for. */
    protected ?GibsProjection $listed_for = null;

    protected int $zoom = 0;

    protected float $centre_x = 0.0;

    protected float $centre_y = 0.0;

    /** @var array{0: float, 1: float}|null The last point of a held left-button drag, in points. */
    protected ?array $drag = null;

    /** The part-notch the wheel turned toward the next zoom level. */
    protected float $wheel = 0.0;

    protected ?Framebuffer $framebuffer = null;

    protected ?RenderingEngine $velvet = null;

    /** @var array<string, Framebuffer> Decoded tiles by tileKey(), oldest first. */
    protected array $tiles = [];

    /** @var array<string, Framebuffer|false> Legends by colour map id; false while one loads or when there is none. */
    protected array $legends = [];

    /** @var array<string, GibsColorMaps> */
    protected array $colormaps = [];

    protected bool $dirty = false;

    /** A view waiting for the canvas to be laid out, or a run to finish, before its tiles are asked for. */
    protected bool $waiting = false;

    protected float $decode_ms = 0.0;

    protected int $decoded = 0;

    protected float $started_at = 0.0;

    public function key(): string
    {
        return 'gibs';
    }

    public function mounted(TKColumn $root): void
    {
        $this->setMainComponents($root);
    }

    public static function projectionName(GibsProjection $projection): string
    {
        return (string) array_search($projection, self::PROJECTIONS, true);
    }

    public function isProjection(string $uuid): bool
    {
        return $uuid === $this->projection?->uuid();
    }

    public function isLayer(string $uuid): bool
    {
        return $uuid === $this->layer?->uuid();
    }

    public function isOverlay(string $uuid): bool
    {
        return $uuid === $this->overlay?->uuid();
    }

    public function isDate(string $uuid): bool
    {
        return $uuid === $this->date?->uuid();
    }

    /** What a navigation button does ('zoom-in', 'west'…), or null for another uuid. */
    public function navigation(string $uuid): ?string
    {
        foreach ($this->buttons as $key => $button) {
            if ($button->uuid() === $uuid) {
                return $key;
            }
        }

        return null;
    }

    /** The projection's capabilities first (once), then the tiles for the view. */
    public function refresh(): void
    {
        $projection = $this->projection();
        if (isset($this->capabilities[$projection->value])) {
            if ($this->listed_for !== $projection) {
                $this->listLayers();
                $this->wholeMap();
            }
            $this->load();

            return;
        }

        new Flow(new LoadGIBSCapabilitiesStart($this, $projection))->run(GlobalVariables::make());
    }

    public function capabilitiesLoaded(GlobalVariables $bag, GibsProjection $projection): void
    {
        $this->capabilities[$projection->value] = $bag->gibs_capabilities;
        $bag->gibs_capabilities = null;
        $this->listLayers();
        $this->wholeMap();
        $this->done($bag, count($this->capabilities[$projection->value]->layers).' GIBS layers in '.self::projectionName($projection).'.');
        // The capabilities run is still settling: the view's tiles are asked for once it has.
        $this->waiting = true;
    }

    /** A form change: the view is drawn again for it. */
    public function changed(string $what): bool
    {
        if ($what === 'projection') {
            $this->refresh();

            return true;
        }
        if (is_null($this->capabilities())) {
            return true;
        }

        match ($what) {
            'layer' => $this->layerChanged(),
            default => $this->load(),
        };

        return true;
    }

    public function isMap(string $uuid): bool
    {
        return $uuid === $this->canvas?->uuid();
    }

    /**
     * The map's pointer mail: a drag moves it, the wheel zooms it or moves it. Nothing happens
     * before the capabilities have loaded.
     */
    public function pointer(PrimitiveMail $mail): bool
    {
        if (is_null($this->capabilities())) {
            return true;
        }

        match (true) {
            $mail instanceof CanvasPressed => $this->drag = PictureViewer::pans($mail->button) ? [$mail->x, $mail->y] : null,
            $mail instanceof CanvasDragged => $this->dragTo($mail->x, $mail->y),
            $mail instanceof CanvasReleased => $this->release(),
            $mail instanceof CanvasWheeled => $this->wheel($mail),
            default => null,
        };

        return true;
    }

    public function navigate(string $where): bool
    {
        if (is_null($this->capabilities())) {
            return true;
        }

        $span = $this->matrix()->pixelSpan();
        [$width, $height] = $this->canvas->pixelSize();
        match ($where) {
            'zoom-in' => $this->zoom = min($this->zoom + 1, $this->deepest()),
            'zoom-out' => $this->zoom = max($this->zoom - 1, 0),
            'west' => $this->centre_x -= $width * $span / 2,
            'east' => $this->centre_x += $width * $span / 2,
            'north' => $this->centre_y += $height * $span / 2,
            'south' => $this->centre_y -= $height * $span / 2,
            'whole' => $this->wholeMap(),
            default => null,
        };
        $this->clampCentre();
        $this->load();

        return true;
    }

    /**
     * A map centre moved against a pointer move of ($dx, $dy) points: $scale device pixels per
     * point, $span map units per pixel. Map y grows north, the canvas's y south.
     * @return array{0: float, 1: float}
     */
    public static function panned(float $x, float $y, float $dx, float $dy, float $scale, float $span): array
    {
        return [$x - $dx * $scale * $span, $y + $dy * $scale * $span];
    }

    /**
     * Whole zoom levels in a wheel turn of $amount notches on top of the $carry left from the last
     * turns; positive is out. A turn the other way drops the carry, so no notch is spent undoing it.
     * @return array{0: int, 1: float} The levels, and the part-notch carried to the next turn.
     */
    public static function wheelLevels(float $carry, float $amount): array
    {
        if ($carry * $amount < 0) {
            $carry = 0.0;
        }
        $total = $carry + $amount;
        $levels = (int) $total;

        return [$levels, $total - $levels];
    }

    /** Every sketch tick: follow the canvas's size, and show what landed since the last one. */
    public function tick(): void
    {
        [$width, $height] = $this->canvas->pixelSize();
        if ($width < 1 || $height < 1) {
            return;
        }

        $framebuffer = $this->canvas->framebuffer('full');
        if ($framebuffer !== $this->framebuffer) {
            $this->framebuffer = $framebuffer;
            $this->velvet = app('drawing')->renderer('velvet', ['framebuffer' => $framebuffer]);
            $this->repaint();
            // A new size is a new view: ask for what it shows that is not held yet.
            if (! is_null($this->capabilities())) {
                $this->waiting = true;
            }
        }
        if ($this->waiting && ! $this->busy) {
            $this->waiting = false;
            $this->load();
        }
        if ($this->dirty) {
            $this->dirty = false;
            $this->canvas->present();
        }
    }

    /** A tile came in: decode it, keep it, and draw it if the view still shows it. */
    public function arrived(array $tile, Response $response): void
    {
        $started = hrtime(true);
        $image = app('images')->decode($response->body());
        $this->decode_ms += (hrtime(true) - $started) / 1e6;
        $this->decoded++;

        $this->tiles[$this->tileKey($tile)] = $image;
        if (count($this->tiles) > self::CACHE) {
            array_shift($this->tiles);
        }

        if ($tile['zoom'] === $this->zoom && $tile['layer'] === $this->currentLayer()->identifier && $tile['time'] === $this->time($this->currentLayer())) {
            [$x, $y] = $this->place($tile['row'], $tile['col']);
            $this->velvet?->frame(fn (RenderingEngine $g) => $g->image($image, $x, $y));
            $this->drawVectors(new Region((int) $x, (int) $y, $image->viewportWidth(), $image->viewportHeight()));
            $this->paintLegend();
            $this->dirty = true;
        }
    }

    /**
     * A vector tile came in: its style evaluated once into a draw list (the tile itself is let go),
     * and the list drawn if the view still shows it.
     */
    public function vectorArrived(array $tile, GibsVectorTile $vector): void
    {
        $style = $this->styleFor($this->capabilities()->layer($tile['layer']));
        if (is_null($style)) {
            return;
        }
        [$list] = GibsVectors::drawList($vector, $style, $tile['view']);
        $this->vectors[$this->tileKey($tile)] = $list;
        if (count($this->vectors) > self::VECTOR_CACHE) {
            array_shift($this->vectors);
        }
        if (in_array($tile, $this->vectorTilesInView(), true)) {
            $this->velvet?->frame(fn (RenderingEngine $g) => $this->drawVectorTile($g, $tile, $list));
            $this->paintLegend();
            $this->dirty = true;
        }
    }

    public function tilesLoaded(GlobalVariables $bag, int $wanted): void
    {
        $seconds = microtime(true) - $this->started_at;
        $loaded = (int) ($bag->gibs_loaded ?? 0);
        $bag->gibs_tiles = null;

        $loaded === 0
            ? $this->empty($bag, "GIBS sent none of the {$wanted} tiles.")
            : $this->done($bag, sprintf('%d of %d tiles in %.1f s, decoded at %.1f ms each.', $loaded, $wanted, $seconds, $this->decode_ms / max(1, $this->decoded)));
    }

    /** Paint what is held for the view, and fetch the tiles it lacks. */
    protected function load(): void
    {
        $capabilities = $this->capabilities();
        if (is_null($capabilities) || is_null($this->velvet)) {
            $this->waiting = true;

            return;
        }

        $layer = $this->currentLayer();
        $time = $this->time($layer);
        $matrix = $this->matrix();
        $range = $matrix->covering($this->viewBox());
        $this->legendFor($layer);
        $this->repaint();
        $this->caption->setText($this->describe($layer, $time, $matrix));

        $wanted = [];
        foreach ($range?->tiles() ?? [] as [$row, $col]) {
            $tile = ['layer' => $layer->identifier, 'set' => $layer->tileMatrixSets[0], 'zoom' => $this->zoom, 'row' => $row, 'col' => $col, 'time' => $time, 'format' => $layer->tileFormat()];
            if (! isset($this->tiles[$this->tileKey($tile)])) {
                $wanted[] = $tile;
            }
        }
        foreach ($this->vectorTilesInView() as $tile) {
            if (! isset($this->vectors[$this->tileKey($tile)])) {
                $wanted[] = $tile;
            }
        }
        if ($wanted === []) {
            return;
        }

        $this->started_at = microtime(true);
        $message = sprintf('Fetching %d tiles of %s…', count($wanted), self::LAYERS[$layer->identifier]);
        new Flow(new LoadGIBSTilesStart($this, $this->projection(), $wanted, $message))->run(GlobalVariables::make());
    }

    /** The background, then every held tile of the view, its overlay and its legend. */
    protected function repaint(): void
    {
        $capabilities = $this->capabilities();
        if (is_null($this->velvet)) {
            return;
        }

        $this->velvet->frame(function (RenderingEngine $g) use ($capabilities): void {
            $g->clear(Color::hex(self::BACKGROUND));
            if (is_null($capabilities)) {
                return;
            }
            $layer = $this->currentLayer();
            $time = $this->time($layer);
            foreach ($this->matrix()->covering($this->viewBox())?->tiles() ?? [] as [$row, $col]) {
                $key = $this->tileKey(['layer' => $layer->identifier, 'zoom' => $this->zoom, 'row' => $row, 'col' => $col, 'time' => $time]);
                if (isset($this->tiles[$key])) {
                    [$x, $y] = $this->place($row, $col);
                    $g->image($this->tiles[$key], $x, $y);
                }
            }
        });
        if (! is_null($capabilities)) {
            $this->drawVectors();
            $this->paintLegend();
        }
        $this->dirty = true;
    }

    /**
     * The overlay's tiles the view needs, at the zoom nearest the view's its tile matrix set has.
     * 'view' is the view's zoom, which the style is evaluated at.
     *
     * @return list<array<string, mixed>>
     */
    protected function vectorTilesInView(): array
    {
        $id = $this->overlays[$this->overlay->selected()] ?? null;
        $layer = is_null($id) ? null : ($this->capabilities()->layers[$id] ?? null);
        if (is_null($layer) || is_null($this->styleFor($layer))) {
            return [];
        }
        $set = $this->capabilities()->tileMatrixSet($layer->tileMatrixSets[0]);
        $zoom = min($this->zoom, $set->zoomLevels() - 1);
        $picked = $this->date->date()?->format('Y-m-d');
        $time = is_null($layer->time) ? null : ($layer->time->includes($picked ?? '') ? $picked : $layer->time->default);

        $tiles = [];
        foreach ($set->matrix($zoom)->covering($this->viewBox())?->tiles() ?? [] as [$row, $col]) {
            $tiles[] = ['layer' => $layer->identifier, 'set' => $set->identifier, 'zoom' => $zoom, 'row' => $row, 'col' => $col, 'time' => $time, 'vector' => true, 'view' => $this->zoom];
        }

        return $tiles;
    }

    /** The overlay layer's style: fetched the first time (null until it lands), then held. */
    protected function styleFor(WmtsLayer $layer): ?GibsVectorStyle
    {
        $id = $layer->linkId('mapbox-gl-style/1.0');
        if (is_null($id)) {
            return null;
        }
        if (! array_key_exists($id, $this->styles)) {
            $this->styles[$id] = false;
            $this->nasa()->gibs()->vectorStyle($id)->async()->then(function (GibsVectorStyle $style) use ($id): void {
                $this->styles[$id] = $style;
                $this->waiting = true;
            })->error(fn () => null);
        }

        return $this->styles[$id] ?: null;
    }

    /**
     * Every held vector tile of the view, each in a frame of its own: the engine keeps a frame's
     * commands for replay(), and a view of polygon tiles in one frame runs to tens of MB.
     */
    protected function drawVectors(?Region $within = null): void
    {
        foreach ($this->vectorTilesInView() as $tile) {
            if (isset($this->vectors[$this->tileKey($tile)])) {
                $list = $this->vectors[$this->tileKey($tile)];
                $this->velvet?->frame(fn (RenderingEngine $g) => $this->drawVectorTile($g, $tile, $list, $within));
            }
        }
    }

    /** One tile's draw list, placed on the view and scaled from style pixels to the canvas's. */
    protected function drawVectorTile(RenderingEngine $g, array $tile, array $list, ?Region $within = null): void
    {
        $box = $this->capabilities()->tileMatrixSet($tile['set'])->matrix($tile['zoom'])->tileBox($tile['row'], $tile['col']);
        $view = $this->viewBox();
        $span = $this->matrix()->pixelSpan();
        [$points] = $this->canvas->size() + [0];

        GibsVectors::draw(
            $g,
            $list,
            ($box->minX - $view->minX) / $span,
            ($view->maxY - $box->maxY) / $span,
            $box->width() / $span,
            $points > 0 ? $g->width() / $points : 1.0,
            $within,
        );
    }

    /** The legend over everything, in a frame of its own. */
    protected function paintLegend(): void
    {
        $this->velvet?->frame(fn (RenderingEngine $g) => $this->drawLegend($g));
    }

    /**
     * The layer's legend, fetched and decoded the first time it is asked for (null until then, and
     * for a layer without a colour map). Its colour map comes along for the caption.
     */
    protected function legendFor(WmtsLayer $layer): ?Framebuffer
    {
        $id = $layer->linkId('colormap/1.3');
        if (is_null($id)) {
            return null;
        }
        if (! array_key_exists($id, $this->legends)) {
            $this->legends[$id] = false;
            $this->nasa()->gibs()->legend($id)->async()->then(function (Response $response) use ($id): void {
                $this->legends[$id] = app('images')->decode($response->body());
                $this->repaint();
            })->error(fn () => null);
            $this->nasa()->gibs()->colormap($id)->async()->then(function (GibsColorMaps $maps) use ($id): void {
                $this->colormaps[$id] = $maps;
                $this->caption->setText($this->describe($this->currentLayer(), $this->time($this->currentLayer()), $this->matrix()));
            })->error(fn () => null);
        }

        return $this->legends[$id] ?: null;
    }

    /** The legend in the bottom-left corner, on a pale card so its dark lettering reads over any map. */
    protected function drawLegend(RenderingEngine $g): void
    {
        $legend = is_null($this->capabilities()) ? null : $this->legendFor($this->currentLayer());
        if (is_null($legend)) {
            return;
        }
        $x = 16;
        $y = $g->height() - $legend->viewportHeight() - 16;
        $g->fillRect($x - 8, $y - 8, $legend->viewportWidth() + 16, $legend->viewportHeight() + 16, Color::rgba(245, 245, 240, 0.88));
        $g->image($legend, $x, $y);
    }

    protected function layerChanged(): void
    {
        $this->layerChosen();
        $this->zoom = min($this->zoom, $this->deepest());
        $this->load();
    }

    /** The picker follows the layer: off for one without time, moved to its default when it has no imagery for the picked day. */
    protected function layerChosen(): void
    {
        $layer = $this->currentLayer();
        $this->date->setEnabled(! is_null($layer->time));
        if (! is_null($layer->time) && ! $layer->time->includes($this->date->date()?->format('Y-m-d') ?? '')) {
            $this->date->setDate(new DateTimeImmutable(substr($layer->time->default ?? $layer->time->latest(), 0, 10)));
        }
    }

    /** List the projection's layers from LAYERS and overlays from OVERLAYS, keeping the current ones when it has them. */
    protected function listLayers(): void
    {
        $layers = $this->capabilities()->layers;
        $current = $this->listed[$this->layer->selected()] ?? null;
        $this->listed = array_values(array_filter(array_keys(self::LAYERS), fn (string $id): bool => isset($layers[$id])));
        $this->listed_for = $this->projection();
        $this->layer->setOptions(array_map(fn (string $id): string => self::LAYERS[$id], $this->listed));
        $this->layer->select(max(0, (int) array_search($current, $this->listed, true)));

        $overlay = $this->overlays[$this->overlay->selected()] ?? null;
        $offered = array_filter(self::OVERLAYS, fn (?string $id): bool => is_null($id) || isset($layers[$id]));
        $this->overlays = array_values($offered);
        $this->overlay->setOptions(array_keys($offered));
        $this->overlay->select(max(0, (int) array_search($overlay, $this->overlays, true)));
        $this->layerChosen();
    }

    protected function dragTo(float $x, float $y): void
    {
        if (is_null($this->drag)) {
            return;
        }

        $this->panBy($x - $this->drag[0], $y - $this->drag[1]);
        $this->drag = [$x, $y];
    }

    /** The drag is over: the tiles it uncovered load on the next tick. */
    protected function release(): void
    {
        $this->drag = null;
        $this->waiting = true;
    }

    protected function wheel(CanvasWheeled $mail): void
    {
        if ($mail->precise && ! Primary::held($mail->modifiers)) {
            $this->panBy(-$mail->dx, -$mail->dy);
            $this->waiting = true;

            return;
        }

        [$levels, $this->wheel] = self::wheelLevels($this->wheel, $mail->precise ? $mail->dy / 50 : $mail->dy);
        if ($levels !== 0) {
            // A positive dy zooms out; a higher level is deeper.
            $this->zoomBy(-$levels);
        }
    }

    /** Move the map with a pointer move of ($dx, $dy) points, drawing the tiles already held. */
    protected function panBy(float $dx, float $dy): void
    {
        [$this->centre_x, $this->centre_y] = self::panned($this->centre_x, $this->centre_y, $dx, $dy, $this->pixelScale(), $this->matrix()->pixelSpan());
        $this->clampCentre();
        $this->repaint();
    }

    protected function zoomBy(int $levels): void
    {
        $this->zoom = max(0, min($this->deepest(), $this->zoom + $levels));
        $this->clampCentre();
        $this->load();
    }

    /** Device pixels per point of the canvas. */
    private function pixelScale(): float
    {
        [$pixels] = $this->canvas->pixelSize();
        [$points] = $this->canvas->size() + [0];

        return $points > 0 ? $pixels / $points : 1.0;
    }

    /** The view that shows the whole projection, centred, at the deepest zoom it fits at. */
    protected function wholeMap(): void
    {
        $extent = $this->extent();
        [$width, $height] = $this->canvas->pixelSize();
        $width = max($width, 480);
        $height = max($height, 300);
        $this->zoom = 0;
        $set = $this->capabilities()->tileMatrixSetOf($this->currentLayer()->identifier);
        while ($this->zoom < $this->deepest() && $extent->width() / $set->matrix($this->zoom + 1)->pixelSpan() <= $width && $extent->height() / $set->matrix($this->zoom + 1)->pixelSpan() <= $height) {
            $this->zoom++;
        }
        $this->centre_x = ($extent->minX + $extent->maxX) / 2;
        $this->centre_y = ($extent->minY + $extent->maxY) / 2;
    }

    protected function clampCentre(): void
    {
        $extent = $this->extent();
        $this->centre_x = max($extent->minX, min($extent->maxX, $this->centre_x));
        $this->centre_y = max($extent->minY, min($extent->maxY, $this->centre_y));
    }

    /** What the projection covers: the globe in degrees for EPSG:4326, else the top tile matrix's grid. */
    protected function extent(): GibsBox
    {
        if ($this->projection() === GibsProjection::EPSG4326) {
            return new GibsBox(-180, -90, 180, 90);
        }
        $top = $this->capabilities()->tileMatrixSetOf($this->currentLayer()->identifier)->matrix(0);
        $span = $top->pixelSpan();

        return new GibsBox($top->topLeftX, $top->topLeftY - $top->matrixHeight * $top->tileHeight * $span, $top->topLeftX + $top->matrixWidth * $top->tileWidth * $span, $top->topLeftY);
    }

    /** The area the canvas shows, its centre snapped to the pixel grid so tiles land on whole pixels. */
    protected function viewBox(): GibsBox
    {
        $matrix = $this->matrix();
        $span = $matrix->pixelSpan();
        [$width, $height] = $this->canvas->pixelSize();
        $left = $matrix->topLeftX + round(($this->centre_x - $width * $span / 2 - $matrix->topLeftX) / $span) * $span;
        $top = $matrix->topLeftY - round(($matrix->topLeftY - ($this->centre_y + $height * $span / 2)) / $span) * $span;

        return new GibsBox($left, $top - $height * $span, $left + $width * $span, $top);
    }

    /** @return array{float, float} Where tile ($row, $col)'s top-left lands on the canvas. */
    protected function place(int $row, int $col): array
    {
        $view = $this->viewBox();
        $box = $this->matrix()->tileBox($row, $col);
        $span = $this->matrix()->pixelSpan();

        return [round(($box->minX - $view->minX) / $span), round(($view->maxY - $box->maxY) / $span)];
    }

    protected function matrix(): WmtsTileMatrix
    {
        return $this->capabilities()->tileMatrixSetOf($this->currentLayer()->identifier)->matrix($this->zoom);
    }

    protected function deepest(): int
    {
        return $this->capabilities()->tileMatrixSetOf($this->currentLayer()->identifier)->zoomLevels() - 1;
    }

    protected function projection(): GibsProjection
    {
        return array_values(self::PROJECTIONS)[$this->projection->selected()] ?? GibsProjection::EPSG4326;
    }

    protected function capabilities(): ?WmtsCapabilities
    {
        return $this->capabilities[$this->projection()->value] ?? null;
    }

    protected function currentLayer(): WmtsLayer
    {
        return $this->capabilities()->layer($this->listed[$this->layer->selected()] ?? $this->listed[0]);
    }

    /** The date the picker shows, for a layer with time; null for one without. */
    protected function time(WmtsLayer $layer): ?string
    {
        return is_null($layer->time) ? null : ($this->date->date()?->format('Y-m-d') ?? $layer->time->default);
    }

    protected function tileKey(array $tile): string
    {
        return implode('/', [$this->projection()->value, $tile['layer'], $tile['time'] ?? '-', $tile['zoom'], $tile['row'], $tile['col'], $tile['view'] ?? '']);
    }

    /** The client the app holds now: Settings may have swapped in a new key since the page opened. */
    protected function nasa(): NasaClient
    {
        return GlobalVariables::make()->nasa;
    }

    protected function describe(WmtsLayer $layer, ?string $time, WmtsTileMatrix $matrix): string
    {
        $view = $this->viewBox();
        $geographic = $this->projection() === GibsProjection::EPSG4326;
        $unit = $geographic ? '°' : ' km';
        $scale = $geographic ? 1.0 : 1000.0;

        $id = $layer->linkId('colormap/1.3');
        $data = isset($this->colormaps[$id]) ? $this->colormaps[$id]->data() : null;

        return sprintf(
            '%s%s · zoom %d of %d, %s per pixel · view %.1f%s × %.1f%s%s',
            $layer->title,
            is_null($time) ? '' : " · {$time}",
            $this->zoom,
            $this->deepest(),
            $geographic ? sprintf('%.4f°', $matrix->pixelSpan()) : sprintf('%.0f m', $matrix->pixelSpan()),
            $view->width() / $scale,
            $unit,
            $view->height() / $scale,
            $unit,
            is_null($data) ? '' : sprintf(' · colour map: %s, %d colours', $data->title ?? $data->units ?? 'data', count($data->entries)),
        );
    }

    protected function setBusy(bool $busy): void
    {
        foreach ($this->buttons as $button) {
            $button->setEnabled(! $busy);
        }
    }
}
