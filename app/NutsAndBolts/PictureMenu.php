<?php

namespace App\NutsAndBolts;

use App\Enums\Outcome;
use App\GlobalVariables;
use Surface\Contracts\Windows\Mail\MenuActivated;
use Surface\Contracts\Windows\Primitives\ImageScaling;
use Surface\Contracts\Windows\Primitives\TKPrimitive;
use Surface\Core\MagicAliases\Toolkit;
use Throwable;

/**
 * A shown picture's context menu: right-click it to save it to the Downloads folder or open it
 * in a window of its own. The picture is the file the media cache already holds, so neither
 * asks the network again.
 */
final class PictureMenu
{
    /** The largest a picture window opens, in points; a smaller picture opens at its own size. */
    public const array WINDOW_MAX = [1100, 800];

    /** The smallest a picture window opens, in points, along each side. */
    public const int WINDOW_MIN = 240;

    /** Menu item ids: "picture.<token>.save" and "picture.<token>.open". */
    protected const string ITEM = '/^picture\.(\d+)\.(save|open)$/';

    /** @var array<int, array{0: string, 1: string}> Token => [file, name]. */
    protected array $targets = [];

    /** Tokens stay unique across every menu, so a choice names one picture. */
    protected static int $tokens = 0;

    /** Picture windows opened so far; each takes the next number in its name. */
    protected static int $windows = 0;

    /**
     * @param string $screen The status bar line the actions report on.
     */
    public function __construct(protected string $screen) {}

    /**
     * Give a shown picture its context menu.
     * @param TKPrimitive $view The image primitive showing it.
     * @param string $file The cached file it shows.
     * @param string $name What to call it: the window title, and the saved file's name.
     */
    public function target(TKPrimitive $view, string $file, string $name): void
    {
        $token = ++self::$tokens;
        $this->targets[$token] = [$file, $name];
        $view->setContextMenu(self::items($token));
    }

    /**
     * Forget every picture: what was shown is gone. A view shown again gets a fresh menu.
     */
    public function clear(): void
    {
        $this->targets = [];
    }

    /**
     * A context menu choice for one of this menu's pictures runs it; anything else is not
     * this menu's.
     */
    public function activated(MenuActivated $mail): bool
    {
        if (! preg_match(self::ITEM, $mail->item, $match) || ! isset($this->targets[(int) $match[1]])) {
            return false;
        }

        [$file, $name] = $this->targets[(int) $match[1]];
        $match[2] === 'save' ? $this->save($file, $name) : $this->open($file, $name);

        return true;
    }

    /**
     * A picture's menu, in the menu profile's item format.
     * @return list<array{id: string, label: string}>
     */
    public static function items(int $token): array
    {
        return [
            ['id' => "picture.{$token}.save", 'label' => 'Download'],
            ['id' => "picture.{$token}.open", 'label' => 'Open in Window'],
        ];
    }

    /**
     * The folder pictures are saved to: the user's Downloads, or home without one.
     */
    public static function downloadsDirectory(?string $home = null): string
    {
        $home ??= getenv('HOME') ?: sys_get_temp_dir();
        $downloads = $home.DIRECTORY_SEPARATOR.'Downloads';

        return is_dir($downloads) ? $downloads : $home;
    }

    /**
     * A file name for a picture: its name with the characters file systems refuse replaced, and
     * the cached file's extension.
     */
    public static function fileName(string $name, string $file): string
    {
        $base = trim((string) preg_replace('/[\/\\\\:*?"<>|\x00-\x1F]+/u', ' ', $name));
        $base = trim((string) preg_replace('/\s+/u', ' ', $base), ' .');
        $base = mb_substr($base === '' ? 'picture' : $base, 0, 120);
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return $extension === '' ? $base : "{$base}.{$extension}";
    }

    /**
     * A path in $directory for $file_name that no file has yet: "name (2).jpg" and so on.
     */
    public static function freePath(string $directory, string $file_name): string
    {
        $path = $directory.DIRECTORY_SEPARATOR.$file_name;
        $base = pathinfo($file_name, PATHINFO_FILENAME);
        $extension = pathinfo($file_name, PATHINFO_EXTENSION);

        for ($n = 2; file_exists($path); $n++) {
            $path = $directory.DIRECTORY_SEPARATOR.$base." ({$n})".($extension === '' ? '' : ".{$extension}");
        }

        return $path;
    }

    /**
     * A window size for a picture of $width × $height: its own size when it fits within
     * WINDOW_MAX, else scaled down to fit, keeping its shape; never below WINDOW_MIN.
     * @return array{0: int, 1: int}
     */
    public static function windowSize(int $width, int $height): array
    {
        if ($width < 1 || $height < 1) {
            return self::WINDOW_MAX;
        }

        [$max_width, $max_height] = self::WINDOW_MAX;
        $scale = min(1.0, $max_width / $width, $max_height / $height);

        return [
            max(self::WINDOW_MIN, (int) round($width * $scale)),
            max(self::WINDOW_MIN, (int) round($height * $scale)),
        ];
    }

    protected function save(string $file, string $name): void
    {
        $path = self::freePath(self::downloadsDirectory(), self::fileName($name, $file));

        if (! is_file($file)) {
            $this->report(Outcome::FAILED, "“{$name}” is no longer cached; load it again to save it.");

            return;
        }

        copy($file, $path)
            ? $this->report(Outcome::DONE, "Saved {$path}.")
            : $this->report(Outcome::FAILED, "Could not save {$path}.");
    }

    protected function open(string $file, string $name): void
    {
        if (! is_file($file)) {
            $this->report(Outcome::FAILED, "“{$name}” is no longer cached; load it again to open it.");

            return;
        }

        $dimensions = @getimagesize($file);
        [$width, $height] = self::windowSize($dimensions[0] ?? 0, $dimensions[1] ?? 0);

        try {
            $window = Toolkit::window()->open('picture-'.++self::$windows, $width, $height);
            $window->setTitle($name);
            $view = $window->column('viewer');
            $view->image('picture', $file)->setScaling(ImageScaling::FIT)->fill();
            $window->present();
            $this->report(Outcome::DONE, "Opened “{$name}” in its own window.");
        } catch (Throwable $e) {
            $this->report(Outcome::FAILED, "Could not open a window for “{$name}”: {$e->getMessage()}");
        }
    }

    protected function report(Outcome $outcome, string $message): void
    {
        GlobalVariables::make()->status_bar?->settle($this->screen, $outcome, $message);
    }
}
