<?php

namespace App\NutsAndBolts;

use App\Enums\Outcome;
use App\GlobalVariables;
use Surface\Contracts\Windows\Mail\MenuActivated;
use Surface\Contracts\Windows\Mail\Services\AlertAnswered;
use Surface\Contracts\Windows\Mail\Services\FilesChosen;
use Surface\Contracts\Windows\Primitives\ImageScaling;
use Surface\Contracts\Windows\Primitives\TKPrimitive;
use Surface\Contracts\Windows\Services\Alert;
use Surface\Contracts\Windows\Services\SaveRequest;
use Surface\Core\MagicAliases\Toolkit;
use Throwable;
use Voyager\Contracts\Signals\NamedSignal;

/**
 * A shown picture's context menu: right-click it to save it where you choose, copy its link, or
 * open it in a window of its own. The picture is the file the media cache already holds, so none
 * of these asks the network again.
 */
final class PictureMenu
{
    /** The largest a picture window opens, in points; a smaller picture opens at its own size. */
    public const array WINDOW_MAX = [1100, 800];

    /** The smallest a picture window opens, in points, along each side. */
    public const int WINDOW_MIN = 240;

    /** Menu item ids: "picture.<token>.save", "picture.<token>.copy" and "picture.<token>.open". */
    protected const string ITEM = '/^picture\.(\d+)\.(save|copy|open)$/';

    /** @var array<int, array{0: string, 1: string, 2: ?string}> Token => [file, name, link]. */
    protected array $targets = [];

    /** @var array<string, array{0: string, 1: string, 2: string}> Save panel id => [file, name, screen]. */
    protected static array $pending = [];

    /** @var array<string, true> The ids of the failure alerts this menu opened. */
    protected static array $alerts = [];

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
     * @param string|null $link Its web address, for Copy Link; null leaves the item out.
     */
    public function target(TKPrimitive $view, string $file, string $name, ?string $link = null): void
    {
        $token = ++self::$tokens;
        $this->targets[$token] = [$file, $name, $link];
        $view->setContextMenu(self::items($token, ! is_null($link)));
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

        [$file, $name, $link] = $this->targets[(int) $match[1]];
        match ($match[2]) {
            'save' => $this->save($file, $name),
            'copy' => $this->copy((string) $link, $name),
            default => $this->open($file, $name),
        };

        return true;
    }

    /**
     * A picture's menu, in the menu profile's item format.
     * @return list<array{id: string, label: string}>
     */
    public static function items(int $token, bool $link): array
    {
        return array_values(array_filter([
            ['id' => "picture.{$token}.save", 'label' => 'Save As…'],
            $link ? ['id' => "picture.{$token}.copy", 'label' => 'Copy Link'] : null,
            ['id' => "picture.{$token}.open", 'label' => 'Open in Window'],
        ]));
    }

    /** Wait for save panel $id's answer: it saves $file, called $name, and reports on $screen. */
    public static function expect(string $id, string $file, string $name, string $screen): void
    {
        self::$pending[$id] = [$file, $name, $screen];
    }

    /**
     * The answer of a save panel or a failure alert this menu opened: a chosen path gets the
     * picture; a cancel does nothing.
     * @return bool Whether the mail was one this menu asked for.
     */
    public static function answered(NamedSignal $mail): bool
    {
        if ($mail instanceof AlertAnswered && isset(self::$alerts[$mail->id])) {
            unset(self::$alerts[$mail->id]);

            return true;
        }
        if (! $mail instanceof FilesChosen || ! isset(self::$pending[$mail->id])) {
            return false;
        }

        [$file, $name, $screen] = self::$pending[$mail->id];
        unset(self::$pending[$mail->id]);
        $path = $mail->paths[0] ?? null;
        if (is_null($path)) {
            return true;
        }

        if (self::saveTo($file, $path)) {
            self::report($screen, Outcome::DONE, "Saved {$path}.");

            return true;
        }

        self::report($screen, Outcome::FAILED, "Could not save “{$name}”.");
        $window = GlobalVariables::make()->window;
        if (! is_null($window)) {
            try {
                $id = Toolkit::window()->dialogs()->alert($window, new Alert(
                    "Could not save “{$name}”.",
                    is_file($file) ? "Stargazer could not write {$path}." : 'The picture is no longer cached. Load it again, then save it.',
                ));
                self::$alerts[$id] = true;
            } catch (Throwable) {
                // The status bar already says it; an alert that cannot open must not end the app.
            }
        }

        return true;
    }

    /**
     * Copy the cached $file to $path, over a file there; false when $file is gone or the copy
     * fails. The app's error handler turns copy()'s warning into an exception, caught here.
     */
    public static function saveTo(string $file, string $path): bool
    {
        try {
            return is_file($file) && copy($file, $path);
        } catch (Throwable) {
            return false;
        }
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
        $window = GlobalVariables::make()->window;
        if (is_null($window)) {
            return;
        }

        try {
            $id = Toolkit::window()->dialogs()->saveFile($window, new SaveRequest(
                title: "Save “{$name}”",
                directory: self::downloadsDirectory(),
                name: self::fileName($name, $file),
            ));
            self::expect($id, $file, $name, $this->screen);
        } catch (Throwable $e) {
            self::report($this->screen, Outcome::FAILED, "Could not open the save panel: {$e->getMessage()}");
        }
    }

    protected function copy(string $link, string $name): void
    {
        Toolkit::window()->clipboard()->setText($link);
        self::report($this->screen, Outcome::DONE, "Copied the link to “{$name}”.");
    }

    protected function open(string $file, string $name): void
    {
        if (! is_file($file)) {
            self::report($this->screen, Outcome::FAILED, "“{$name}” is no longer cached; load it again to open it.");

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
            self::report($this->screen, Outcome::DONE, "Opened “{$name}” in its own window.");
        } catch (Throwable $e) {
            self::report($this->screen, Outcome::FAILED, "Could not open a window for “{$name}”: {$e->getMessage()}");
        }
    }

    protected static function report(string $screen, Outcome $outcome, string $message): void
    {
        GlobalVariables::make()->status_bar?->settle($screen, $outcome, $message);
    }
}
