<?php

namespace App\Services\APOD;

use App\Concerns\TracksRuns;
use App\GlobalVariables;
use App\Enums\Outcome;
use App\NutsAndBolts\FetchFailure;
use ProjectSaturnStudios\Stargazer\APOD\ApodAPIService;
use App\Runner\Controllers\APODController;
use App\Services\NasaService;
use App\Workflows\APOD\LoadAPODGallery\LoadAPODGalleryStart;
use App\NutsAndBolts\MediaSource;
use App\NutsAndBolts\PictureViewer;
use ProjectSaturnStudios\Stargazer\APOD\DataObjects\AstronomyPicture;
use ReflectionException;
use Surface\Contracts\Windows\Primitives\Align;
use Surface\Contracts\Windows\Primitives\ImageScaling;
use Surface\Contracts\Windows\Primitives\TKImage;
use Surface\Contracts\Windows\Primitives\TKLabel;
use Voyager\NutsAndBolts\Collection;
use Surface\Contracts\Windows\Mail\MenuToggled;
use Surface\Contracts\Windows\Mail\MenuActivated;
use Surface\Contracts\Windows\Mail\View\ValueChanged;
use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\DateChanged;
use Surface\Contracts\Windows\Mail\View\Toggled;
use Voyager\Workflows\Flow;
use DateMalformedStringException;
use DateInvalidTimeZoneException;
use App\Concerns\APOD\APODContent;
use Surface\Contracts\Windows\Primitives\TKColumn;
use App\Workflows\APOD\LoadAPODDay\LoadAPODDayStart;

class APODService extends NasaService
{
    use APODContent;

    /** Gallery cells per grid row. */
    protected const int GALLERY_COLUMNS = 3;

    /** @var array<string, string> Open-button uuid => the APOD date it opens. */
    protected array $open_dates = [];

    /** @var array<int, array{0: TKImage, 1: TKLabel}> Gallery cell index => [thumb, caption]. */
    protected array $cell_views = [];

    /** @var array<int, string> Gallery cell index => its picture's name, for the right-click menu. */
    protected array $cell_names = [];

    /** @var array<int, ?string> Gallery cell index => its picture's article, for Copy Link. */
    protected array $cell_links = [];

    /** The day the single view is showing, captioned. */
    protected ?AstronomyPicture $current = null;

    public function key(): string
    {
        return 'apod';
    }

    /**
     * @throws DateInvalidTimeZoneException
     * @throws DateMalformedStringException
     * @throws ReflectionException
     */
    public function mounted(TKColumn $root): void
    {
        $this->setMainComponents($root);
    }

    public function caption(AstronomyPicture $picture): void
    {
        $this->current = $picture;
        $this->title_label->setText($picture->title ?? 'Untitled');
        $this->meta->setText($picture->date.(is_null($picture->copyright) ? '' : ' · © '.trim($picture->copyright)));
        $this->explanation->setText($picture->explanation ?? '');
    }

    public function updateDate(DateChanged $signal): bool
    {
        $results = false;

        if($signal->name() == 'view.date-changed.stargazer.shell.body.screens.apod.form.date')
        {
            $bag = GlobalVariables::make();
            $warn = $bag->warn;
            $date = $this->date()->date();
            $date = date('Y-m-d', $date->getTimestamp());
            $warn("Changed date to {$date}");

            $results = true;

        }
        return $results;
    }

    /**
     * The HD box: the day shown loads again in the quality picked. The gallery's previews keep theirs.
     */
    public function updateHd(Toggled $signal, APODController $controller): bool
    {
        $results = false;

        if($signal->name() == 'view.toggled.stargazer.shell.body.screens.apod.form.hd')
        {
            if (! $controller->isShowingGallery()) {
                $controller->refresh();
            }

            $results = true;
        }

        return $results;
    }

    public function reloadDay(ButtonClicked $signal, APODController $controller): bool
    {
        $date = $signal->uuid === $this->previous?->uuid()
            ? $this->dayBefore()
            : $this->galleryDate($signal->uuid);

        is_null($date) ? $controller->refresh() : $controller->openDay($date);

        return true;
    }

    public function menuReaction(MenuToggled $signal, APODController $controller): bool
    {
        $mode = match ($signal->name()) {
            'menu.toggled.stargazer.grid' => true,
            'menu.toggled.stargazer.single' => false,
            default => null,
        };

        if (! is_null($mode)) {
            $controller->showGallery($mode);
        }

        return ! is_null($mode);
    }

    /** The gallery count as its label reads it: "1 picture", "6 pictures". */
    public static function countLabel(int $count): string
    {
        return $count === 1 ? '1 picture' : "{$count} pictures";
    }

    /** The status line of a loaded gallery: "1 picture, … ; 1 preview loaded." */
    public static function galleryDone(int $pictures, string $first, string $last, int $previews): string
    {
        return sprintf('%s, %s to %s; %d %s loaded.', self::countLabel($pictures), $first, $last, $previews, $previews === 1 ? 'preview' : 'previews');
    }

    public function isCount(string $uuid): bool
    {
        return $uuid === $this->count?->uuid();
    }

    /** The gallery slider moved: its label follows. */
    public function countChanged(ValueChanged $mail): bool
    {
        $this->count_label->setText(self::countLabel((int) round($mail->value)));

        return true;
    }

    /**
     * How many days the gallery asks for: the slider's value.
     * @return int
     */
    public function galleryCount(): int
    {
        return (int) round($this->count->value());
    }

    /**
     * Swap the single day for the gallery, or back.
     * @param bool $on
     * @return void
     */
    public function showGalleryPanel(bool $on): void
    {
        $this->single->setVisible(! $on);
        $this->gallery->setVisible($on);
    }

    /**
     * A cell per picture, captioned, in rows of GALLERY_COLUMNS; the pictures come later.
     * @param Collection<int, AstronomyPicture> $pictures
     * @return array<int, array{0: int, 1: MediaSource}> What to download, per cell.
     */
    public function layoutGallery(Collection $pictures): array
    {
        $sources = [];
        $this->clearCells();

        foreach ($pictures->values()->all() as $i => $picture) {
            /** @var AstronomyPicture $picture */
            $this->cells->at(intdiv($i, self::GALLERY_COLUMNS), $i % self::GALLERY_COLUMNS);
            $cell = $this->cells->column("cell-{$i}", 4, 4);
            $thumb = $cell->image('thumb');
            $thumb->setScaling(ImageScaling::FIT)->minSize(220, 150);
            $caption = $cell->label('caption', "{$picture->date} · {$picture->title}");
            $caption->setWrap(true);
            $open = $cell->button('open', 'Open');
            $open->align(Align::START);
            $this->open_dates[$open->uuid()] = $picture->date;
            $this->cell_views[$i] = [$thumb, $caption];
            $this->cell_names[$i] = self::pictureName($picture);
            $this->cell_links[$i] = $picture->permalink;

            $url = $picture->media_type === 'image' ? $picture->url : $picture->thumbnail_url;
            if (! is_null($url)) {
                $sources[$i] = [$i, MediaSource::of($url)];
            } else {
                $caption->setText("{$picture->date} · {$picture->title} (no preview)");
            }
        }

        return $sources;
    }

    /**
     * A cell's picture landed, or failed for good (null).
     * @param int $index
     * @param string|null $path
     * @return void
     */
    public function thumbnailLanded(int $index, ?string $path): void
    {
        [$thumb, $caption] = $this->cell_views[$index];
        if (is_null($path)) {
            $caption->setText($caption->text().' (image unavailable)');

            return;
        }

        $thumb->setFile($path);
        $this->gallery_menu->target($thumb, $path, $this->cell_names[$index], $this->cell_links[$index] ?? null);
    }

    public function galleryLoaded(GlobalVariables $bag): void
    {
        $first = $bag->pictures->first()?->date;
        $last = $bag->pictures->last()?->date;
        $this->done($bag, self::galleryDone($bag->pictures->count(), (string) $first, (string) $last, count($bag->thumbnails)));
    }

    /**
     * The day a gallery cell's Open button opens.
     * @param string $uuid
     * @return string|null
     */
    public function galleryDate(string $uuid): ?string
    {
        return $this->open_dates[$uuid] ?? null;
    }

    protected function clearCells(): void
    {
        foreach ($this->cells->children() as $cell) {
            $cell->remove();
        }

        $this->open_dates = [];
        $this->cell_views = [];
        $this->cell_names = [];
        $this->cell_links = [];
        $this->gallery_menu->clear();
    }

    /**
     * A failed day load: what went wrong, in place of the media. A day the API has no picture
     * for (404) clears the caption; a fetched day whose file failed keeps it and links the item.
     * @param GlobalVariables $bag
     * @param string $date The day that was asked for.
     */
    public function showFailure(GlobalVariables $bag, string $date): void
    {
        $this->single_menu->clear();
        $this->video?->pause()->hide();
        $this->picture->hide();
        $this->link->hide();

        /** @var FetchFailure|null $failure */
        $failure = $bag->failure ?? null;
        $fetched = ($bag->picture ?? null) instanceof AstronomyPicture && $bag->picture->date === $date;
        $not_found = $failure?->status === 404;

        if (! $fetched) {
            $this->title_label->setText($not_found ? "No picture for {$date}" : "Couldn't load {$date}");
            $this->meta->setText('');
            $this->explanation->setText('');
        }

        $this->notice_text->setText(match (true) {
            $fetched => "The picture for {$date} couldn't be downloaded: {$bag->error}",
            $not_found && $date >= today()->format('Y-m-d') => "Today's Astronomy Picture of the Day isn't up yet. NASA posts it around midnight US Eastern; try Load again in a few minutes, or look at the day before.",
            $not_found => "NASA has no Astronomy Picture of the Day for {$date}.",
            default => (string) ($bag->error ?? Outcome::FAILED->defaultMessage()),
        });

        if ($fetched) {
            $this->link->setText('Open: '.($bag->picture->permalink ?? $bag->picture->url))->show();
        }

        $this->previous->setVisible(! $fetched && $date > ApodAPIService::FIRST_DAY);
        $this->notice->show();
        // The media area stays only to carry the link to a fetched day's item.
        $this->media_view->setVisible($fetched);
    }

    /**
     * A loaded day: the media area back, the failure notice gone.
     */
    protected function showMedia(): void
    {
        $this->notice->hide();
        $this->media_view->show();
    }

    /**
     * The day before the picked one, or null before APOD's first day.
     */
    protected function dayBefore(): ?string
    {
        $day = $this->date()->date()?->modify('-1 day')->format('Y-m-d');

        return is_null($day) || $day < ApodAPIService::FIRST_DAY ? null : $day;
    }

    public function showPicture(string $file): void
    {
        $this->showMedia();
        $this->video?->pause()->hide();
        $this->link->hide();
        $this->single_menu->clear();
        if (! $this->picture->show($file)) {
            $this->picture->hide();
            $this->link->setText('This picture is too large to show in the memory PHP has. Open: '.($this->current?->permalink ?? $this->current?->url ?? ''))->show();

            return;
        }
        if (! is_null($this->current)) {
            $this->single_menu->target($this->picture->view(), $file, self::pictureName($this->current), $this->current->permalink);
        }
    }

    /** The day's zoomable picture, which sees view mail before the page does. */
    public function viewer(): PictureViewer
    {
        return $this->picture;
    }

    /**
     * Zoomed past the shown picture's pixels: its HD copy, fetched once into the media cache, takes
     * its place. Nothing when the day has no HD copy or it is already the one shown.
     */
    public function sharpenPicture(string $file): void
    {
        $picture = $this->current;
        $hd = $picture?->media_type === 'image' ? $picture->hdurl : null;
        if (is_null($hd) || $hd === $picture->url || $file === $this->media->path($hd)) {
            return;
        }

        $status = GlobalVariables::make()->status_bar;
        $status?->busy($this->key(), 'Fetching the HD picture to zoom into…');
        $this->media->fetch(MediaSource::of($hd))
            ->then(function (string $path) use ($picture, $status): void {
                if ($this->current !== $picture || ! $this->picture->sharpen($path)) {
                    $status?->settle($this->key(), Outcome::DONE, $this->current === $picture ? 'The HD picture is too large for the memory PHP has; zooming the standard one.' : '');

                    return;
                }
                $this->single_menu->clear();
                $this->single_menu->target($this->picture->view(), $path, self::pictureName($picture).' (HD)', $picture->permalink);
                $status?->settle($this->key(), Outcome::DONE, "{$picture->date}: {$picture->title} (HD)");
            })
            ->error(fn () => $status?->settle($this->key(), Outcome::FAILED, 'The HD picture could not be downloaded; zooming the standard one.'));
    }

    public function showVideo(?string $file, string $url): void
    {
        if (is_null($this->video) || is_null($file)) {
            $this->showLink($url, null);

            return;
        }

        $this->showMedia();
        $this->single_menu->clear();
        $this->picture->hide();
        $this->link->hide();
        $this->video->setFile($file)->show();
        $this->video->play();
    }

    public function showLink(string $url, ?string $thumbnail): void
    {
        $this->showMedia();
        $this->video?->pause()->hide();
        $this->single_menu->clear();
        $shown = ! is_null($thumbnail) && $this->picture->show($thumbnail);
        $this->picture->setVisible($shown);
        if ($shown && ! is_null($this->current)) {
            $this->single_menu->target($this->picture->view(), $thumbnail, self::pictureName($this->current).' (preview)', $this->current->permalink);
        }
        $this->link->setText("Watch: {$url}")->show();
    }

    /**
     * A choice from the day's picture's or a gallery picture's context menu.
     */
    public function menuActivated(MenuActivated $signal): bool
    {
        return $this->single_menu->activated($signal) || $this->gallery_menu->activated($signal);
    }

    /**
     * What a day's picture is called in its window title and saved file name.
     */
    protected static function pictureName(AstronomyPicture $picture): string
    {
        return trim("APOD {$picture->date} ".($picture->title ?? ''));
    }
}
