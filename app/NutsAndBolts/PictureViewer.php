<?php

namespace App\NutsAndBolts;

use Closure;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\Filter;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\HumanInput\MouseButton;
use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\CanvasClicked;
use Surface\Contracts\Windows\Mail\View\CanvasDragged;
use Surface\Contracts\Windows\Mail\View\CanvasPressed;
use Surface\Contracts\Windows\Mail\View\CanvasReleased;
use Surface\Contracts\Windows\Mail\View\CanvasWheeled;
use Surface\Contracts\Windows\Mail\View\PrimitiveMail;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKCanvas;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKLabel;
use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup;
use Surface\NutsAndBolts\Color;
use Throwable;
use Voyager\Contracts\Signals\NamedSignal;

/**
 * A picture you can zoom into: decoded once by app('images'), drawn by VelvetGE on a canvas at
 * whatever scale the view asks for. A click zooms in twice at the point clicked; Zoom out and Fit
 * step back. A left-button drag moves the picture; the wheel zooms about the view's centre; a
 * trackpad scroll moves the picture, or zooms with ⌘/Ctrl held. Zooming past the picture's own pixels asks, once per picture, for a sharper copy
 * (the page's $sharper hook), which replaces it in place without moving the view. A picture
 * too big for the memory left is never decoded; the view keeps what it has.
 */
final class PictureViewer
{
    /** The deepest zoom, in canvas pixels per picture pixel. */
    public const float MAX_ZOOM = 8.0;

    protected const string BACKGROUND = '#111114';

    protected TKColumn $root;

    protected TKCanvas $canvas;

    protected TKButton $zoom_out;

    protected TKButton $fit;

    protected TKLabel $readout;

    protected ?Framebuffer $framebuffer = null;

    protected ?RenderingEngine $velvet = null;

    protected ?Framebuffer $picture = null;

    protected ?string $file = null;

    /** Canvas pixels per picture pixel; 0 until the view is fitted. */
    protected float $zoom = 0.0;

    /** The picture point at the view's centre, in picture pixels. */
    protected float $centre_x = 0.0;

    protected float $centre_y = 0.0;

    protected bool $fitted = true;

    protected bool $dirty = false;

    /** Whether this picture already asked for a sharper copy. */
    protected bool $asked = false;

    /** @var array{0: float, 1: float}|null The last point of a held left-button drag, in points. */
    protected ?array $drag = null;

    /**
     * @param Closure|null $sharper (string $file): void — the shown file wants a sharper copy; the
     *                              page fetches one and hands it to sharpen(), or does nothing.
     */
    public function __construct(TKPrimitiveGroup $in, string $name, protected ?Closure $sharper = null)
    {
        $this->root = $in->column($name, 4);
        $this->root->fill();
        $this->canvas = $this->root->canvas('canvas');
        $this->canvas->fill()->minSize(480, 300);
        $this->canvas->watchPointer();

        $controls = $this->root->row('controls', 8);
        $this->zoom_out = $controls->button('zoom-out', 'Zoom out');
        $this->fit = $controls->button('fit', 'Fit');
        $this->readout = $controls->label('readout', '');
        $this->readout->setTextColor(Color::hex('#8a8a8a'));
        $this->readout->fill(true, false);
    }

    /** The canvas, for whatever else attaches to the picture (its context menu). */
    public function view(): TKCanvas
    {
        return $this->canvas;
    }

    public function file(): ?string
    {
        return $this->file;
    }

    public function isVisible(): bool
    {
        return $this->root->isVisible();
    }

    public function setVisible(bool $visible): static
    {
        $this->root->setVisible($visible);

        return $this;
    }

    public function hide(): static
    {
        return $this->setVisible(false);
    }

    /**
     * Show a picture, fitted to the view. With $keep_view, a picture the same size as the one shown
     * keeps the zoom and centre (frames of one scene, scrubbed through).
     * @return bool Whether it could be decoded and shown.
     */
    public function show(string $file, bool $keep_view = false): bool
    {
        $picture = $this->decode($file);
        if (is_null($picture)) {
            return false;
        }

        $same = ! is_null($this->picture)
            && $picture->viewportWidth() === $this->picture->viewportWidth()
            && $picture->viewportHeight() === $this->picture->viewportHeight();
        $this->picture = $picture;
        $this->file = $file;
        $this->asked = false;
        if (! ($keep_view && $same)) {
            $this->fitted = true;
        }
        $this->root->show();
        $this->layout();
        $this->paint();

        return true;
    }

    /**
     * A sharper copy of the shown picture: it takes the picture's place, the view held on the
     * same point at the same apparent size.
     * @return bool Whether it fit in memory and was shown.
     */
    public function sharpen(string $file): bool
    {
        if (is_null($this->picture)) {
            return false;
        }

        $old = $this->picture->viewportWidth();
        $this->picture = null;
        $picture = $this->decode($file);
        if (is_null($picture)) {
            // Too big, or unreadable: the copy shown stays.
            $this->picture = $this->decode((string) $this->file);

            return false;
        }

        $ratio = $picture->viewportWidth() / max(1, $old);
        $this->picture = $picture;
        $this->file = $file;
        $this->centre_x *= $ratio;
        $this->centre_y *= $ratio;
        $this->zoom /= $ratio;
        $this->paint();

        return true;
    }

    /**
     * View mail: a click on the canvas zooms in there, a drag moves the picture, the wheel zooms or
     * moves it; Zoom out and Fit step back.
     * @return bool Whether it was this viewer's.
     */
    public function handles(NamedSignal $mail): bool
    {
        $mine = $mail instanceof PrimitiveMail && $mail->uuid() === $this->canvas->uuid();

        return match (true) {
            $mine && $mail instanceof CanvasClicked => $this->zoomAt($mail->x, $mail->y, 2.0),
            $mine && $mail instanceof CanvasPressed => $this->press($mail),
            $mine && $mail instanceof CanvasDragged => $this->dragTo($mail->x, $mail->y),
            $mine && $mail instanceof CanvasReleased => $this->release(),
            $mine && $mail instanceof CanvasWheeled => $this->wheel($mail),
            $mail instanceof ButtonClicked && $mail->uuid() === $this->zoom_out->uuid() => $this->zoomAt(null, null, 0.5),
            $mail instanceof ButtonClicked && $mail->uuid() === $this->fit->uuid() => $this->refit(),
            default => false,
        };
    }

    /** Every sketch tick: follow the canvas's size, and show what changed. */
    public function tick(): void
    {
        [$width, $height] = $this->canvas->pixelSize();
        if ($width < 1 || $height < 1 || ! $this->root->isVisible()) {
            return;
        }

        $framebuffer = $this->canvas->framebuffer('full');
        if ($framebuffer !== $this->framebuffer) {
            $this->framebuffer = $framebuffer;
            $this->velvet = app('drawing')->renderer('velvet', ['framebuffer' => $framebuffer]);
            $this->layout();
            $this->paint();
        }

        if ($this->dirty) {
            $this->dirty = false;
            $this->canvas->present();
        }
    }

    /**
     * Zoom by $factor, keeping the picture point under ($x, $y) (points in the canvas) where it
     * is; the view's centre when no point is given.
     */
    protected function zoomAt(?float $x, ?float $y, float $factor): bool
    {
        if (is_null($this->picture) || is_null($this->framebuffer)) {
            return true;
        }

        [$width, $height] = $this->canvas->pixelSize();
        $scale = $this->pixelScale();
        $px = is_null($x) ? $width / 2 : $x * $scale;
        $py = is_null($y) ? $height / 2 : $y * $scale;

        // The picture point under the pointer stays under it.
        $point_x = $this->centre_x + ($px - $width / 2) / $this->zoom;
        $point_y = $this->centre_y + ($py - $height / 2) / $this->zoom;
        $this->zoom = max($this->fitZoom(), min(self::MAX_ZOOM, $this->zoom * $factor));
        $this->centre_x = $point_x - ($px - $width / 2) / $this->zoom;
        $this->centre_y = $point_y - ($py - $height / 2) / $this->zoom;
        $this->fitted = $this->zoom <= $this->fitZoom();
        $this->clamp();
        $this->paint();

        // Past the picture's own pixels: a sharper copy is worth asking for, once.
        if ($this->zoom > 1.0 && ! $this->asked && ! is_null($this->sharper)) {
            $this->asked = true;
            ($this->sharper)((string) $this->file);
        }

        return true;
    }

    protected function press(CanvasPressed $mail): bool
    {
        $this->drag = self::pans($mail->button) ? [$mail->x, $mail->y] : null;

        return true;
    }

    /** Move by the distance from the last point, so a sharper copy landing mid-drag keeps in step. */
    protected function dragTo(float $x, float $y): bool
    {
        if (! is_null($this->drag)) {
            $this->panBy($x - $this->drag[0], $y - $this->drag[1]);
            $this->drag = [$x, $y];
        }

        return true;
    }

    protected function release(): bool
    {
        $this->drag = null;

        return true;
    }

    protected function wheel(CanvasWheeled $mail): bool
    {
        if ($mail->precise && ! Primary::held($mail->modifiers)) {
            $this->panBy(-$mail->dx, -$mail->dy);

            return true;
        }

        return $this->zoomAt(null, null, self::wheelFactor($mail->dy, $mail->precise));
    }

    /** Move the picture with a pointer move of ($dx, $dy) points; the clamp holds a fitted one still. */
    protected function panBy(float $dx, float $dy): void
    {
        if (is_null($this->picture) || is_null($this->framebuffer)) {
            return;
        }

        $scale = $this->pixelScale();
        $this->centre_x = self::panned($this->centre_x, $dx, $scale, $this->zoom);
        $this->centre_y = self::panned($this->centre_y, $dy, $scale, $this->zoom);
        $this->clamp();
        $this->paint();
    }

    protected function refit(): bool
    {
        $this->fitted = true;
        $this->layout();
        $this->paint();

        return true;
    }

    /** A fitted view follows the canvas: the whole picture, centred. */
    protected function layout(): void
    {
        if (is_null($this->picture) || is_null($this->framebuffer)) {
            return;
        }

        if ($this->fitted) {
            $this->zoom = $this->fitZoom();
            $this->centre_x = $this->picture->viewportWidth() / 2;
            $this->centre_y = $this->picture->viewportHeight() / 2;
        }
        $this->zoom = max($this->zoom, $this->fitZoom());
        $this->clamp();
    }

    protected function fitZoom(): float
    {
        [$width, $height] = $this->canvas->pixelSize();

        return min($width / max(1, $this->picture->viewportWidth()), $height / max(1, $this->picture->viewportHeight()));
    }

    /** Keep the picture over the view: centred along a side it does not fill, else edge to edge. */
    protected function clamp(): void
    {
        [$width, $height] = $this->canvas->pixelSize();
        $half_x = $width / 2 / $this->zoom;
        $half_y = $height / 2 / $this->zoom;
        $picture_width = $this->picture->viewportWidth();
        $picture_height = $this->picture->viewportHeight();

        $this->centre_x = $picture_width <= 2 * $half_x ? $picture_width / 2 : max($half_x, min($picture_width - $half_x, $this->centre_x));
        $this->centre_y = $picture_height <= 2 * $half_y ? $picture_height / 2 : max($half_y, min($picture_height - $half_y, $this->centre_y));
    }

    protected function paint(): void
    {
        if (is_null($this->velvet)) {
            return;
        }

        $picture = $this->picture;
        [$width, $height] = $this->canvas->pixelSize();
        $zoom = $this->zoom;
        $x = $width / 2 - $this->centre_x * $zoom;
        $y = $height / 2 - $this->centre_y * $zoom;

        $this->velvet->frame(function (RenderingEngine $g) use ($picture, $x, $y, $zoom): void {
            $g->clear(Color::hex(self::BACKGROUND));
            if (! is_null($picture)) {
                $g->image($picture, $x, $y, $picture->viewportWidth() * $zoom, $picture->viewportHeight() * $zoom, filter: Filter::LINEAR);
            }
        });
        $this->dirty = true;

        $this->readout->setText(is_null($picture) ? '' : sprintf(
            '%d × %d · %d%% · click or scroll to zoom, drag to move',
            $picture->viewportWidth(),
            $picture->viewportHeight(),
            (int) round($zoom * 100),
        ));
        $this->zoom_out->setEnabled(! is_null($picture) && ! $this->fitted);
        $this->fit->setEnabled(! is_null($picture) && ! $this->fitted);
    }

    /** Whether a press of $button moves the picture: the left button only; the right opens its menu. */
    public static function pans(MouseButton $button): bool
    {
        return $button === MouseButton::LEFT;
    }

    /**
     * A centre moved against a pointer move of $delta points: $scale device pixels per point, at
     * $zoom canvas pixels per picture pixel.
     */
    public static function panned(float $centre, float $delta, float $scale, float $zoom): float
    {
        return $centre - $delta * $scale / $zoom;
    }

    /** The zoom factor of a wheel turn: 1.25 per notch, 1.01 per trackpad point; down zooms out. */
    public static function wheelFactor(float $dy, bool $precise): float
    {
        return ($precise ? 1.01 : 1.25) ** -$dy;
    }

    /** A file decoded, unless its pixels would not fit in the memory left. */
    protected function decode(string $file): ?Framebuffer
    {
        $size = @getimagesize($file);
        if ($size === false || ! self::fits($size[0], $size[1])) {
            return null;
        }

        try {
            return app('images')->decode((string) file_get_contents($file));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Whether a $width × $height picture can be decoded in the memory left: its RGBA pixels and
     * the file read in beside them, with room to spare for the frame that draws it.
     */
    public static function fits(int $width, int $height, ?int $limit = null, ?int $used = null): bool
    {
        $limit ??= self::bytes((string) ini_get('memory_limit'));
        if ($limit < 0) {
            return true;
        }
        $used ??= memory_get_usage();

        return $width > 0 && $height > 0 && $width * $height * 4 * 1.5 < $limit - $used;
    }

    /** A php.ini size ("128M", "1G", "-1") in bytes; -1 for no limit. */
    public static function bytes(string $size): int
    {
        $size = trim($size);
        if ($size === '' || $size === '-1') {
            return -1;
        }
        $number = (int) $size;

        return match (strtoupper(substr($size, -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }

    /** Device pixels per point of the canvas. */
    protected function pixelScale(): float
    {
        [$pixels] = $this->canvas->pixelSize();
        [$points] = $this->canvas->size() + [0];

        return $points > 0 ? $pixels / $points : 1.0;
    }
}
