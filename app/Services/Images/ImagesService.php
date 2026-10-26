<?php

namespace App\Services\Images;

use App\Concerns\Images\ImagesContent;
use App\GlobalVariables;
use App\Enums\Outcome;
use App\NutsAndBolts\StatusBar;
use App\NutsAndBolts\PictureViewer;
use App\Models\PersistentVariable;
use App\NutsAndBolts\MediaSource;
use App\NutsAndBolts\MediaVariant;
use App\Services\NasaService;
use ProjectSaturnStudios\Stargazer\ImageLibrary\DataObjects\ImageItemData;
use ProjectSaturnStudios\Stargazer\ImageLibrary\DataObjects\ImageSearchItem;
use ProjectSaturnStudios\Stargazer\ImageLibrary\DataObjects\ImageSearchPage;
use ProjectSaturnStudios\Stargazer\ImageLibrary\Enums\ImageMediaType;
use Surface\Contracts\Windows\Mail\MenuActivated;
use Surface\Contracts\Windows\Primitives\TKColumn;
use ReflectionException;
use Throwable;
use Voyager\Workflows\SharedBag;

/**
 * NASA's Image and Video Library: search, then view a result's picture, play its video with
 * its own controls, or follow its audio link.
 */
class ImagesService extends NasaService
{
    use ImagesContent;

    protected const array MEDIA = ['Images' => ImageMediaType::IMAGE, 'Videos' => ImageMediaType::VIDEO, 'Audio' => ImageMediaType::AUDIO];

    /** The persistent variable holding the last search: its query and media type. */
    public const string LAST_SEARCH = 'images.last_search';

    /** What the first search asks for, before any search is remembered. */
    protected const string FIRST_QUERY = 'jwst';

    /** How often a playing video moves the seek bar, in seconds. */
    protected const float SEEK_EVERY = 0.25;

    /** @var list<ImageItemData> The table's rows, in order. */
    protected array $items = [];

    protected float $seeked_at = 0.0;

    protected bool $ranged = false;

    /** The item the viewer is showing. */
    protected ?ImageItemData $current = null;

    /** @var list<string> The shown item's asset manifest files. */
    protected array $hrefs = [];

    public function key(): string
    {
        return 'images';
    }

    /**
     * @throws ReflectionException
     */
    public function mounted(TKColumn $root): void
    {
        $this->setMainComponents($root);
    }

    public function query(): string
    {
        return trim($this->query->value());
    }

    public function mediaType(): ImageMediaType
    {
        return array_values(self::MEDIA)[$this->media_type->selected()];
    }

    /**
     * Store a search's query and media type, written in a pool worker; the page opens on it
     * next time.
     * @throws ReflectionException
     */
    public function rememberSearch(string $query, ImageMediaType $type): void
    {
        $bag = GlobalVariables::make();

        PersistentVariable::query()->via()
            ->updateOrCreate(['key' => self::LAST_SEARCH], ['value' => ['query' => $query, 'media' => $type->value]])
            ->error(fn (Throwable $e) => ($bag->warn)("Last search not saved: {$e->getMessage()}"));
    }

    /**
     * The search the form opens on: the last one stored, else FIRST_QUERY for images.
     * @return array{query: string, media: int} media: the dropdown index.
     * @throws ReflectionException
     */
    protected function lastSearch(): array
    {
        $stored = PersistentVariable::recall(self::LAST_SEARCH);
        $query = is_array($stored) && is_string($stored['query'] ?? null) && trim($stored['query']) !== '' ? $stored['query'] : self::FIRST_QUERY;
        $type = is_array($stored) && is_string($stored['media'] ?? null) ? ImageMediaType::tryFrom($stored['media']) : null;
        $media = array_search($type ?? ImageMediaType::IMAGE, array_values(self::MEDIA), true);

        return ['query' => $query, 'media' => $media === false ? 0 : $media];
    }

    /**
     * The item behind a table row; null for no row, or a row the table no longer has.
     */
    public function item(?int $row): ?ImageItemData
    {
        return is_null($row) ? null : ($this->items[$row] ?? null);
    }

    /**
     * Whether the toolkit gave the stage a video view; without one, videos show as a link.
     */
    public function hasVideoView(): bool
    {
        return ! is_null($this->video);
    }

    public function isQuery(string $uuid): bool
    {
        return $uuid === $this->query?->uuid();
    }

    public function isSearch(string $uuid): bool
    {
        return $uuid === $this->search?->uuid();
    }

    public function isResults(string $uuid): bool
    {
        return $uuid === $this->results?->uuid();
    }

    public function isPlay(string $uuid): bool
    {
        return $uuid === $this->play?->uuid();
    }

    public function isSeek(string $uuid): bool
    {
        return $uuid === $this->seek?->uuid();
    }

    public function isMute(string $uuid): bool
    {
        return $uuid === $this->mute?->uuid();
    }

    public function isLoop(string $uuid): bool
    {
        return $uuid === $this->loop?->uuid();
    }

    public function isVideo(string $uuid): bool
    {
        return $uuid === $this->video?->uuid();
    }

    /**
     * A search page landed: one row per item that carries data, and the outcome.
     */
    public function showResults(GlobalVariables $bag, string $query, ImageMediaType $type): void
    {
        /** @var ImageSearchPage $page */
        $page = $bag->page;
        $this->items = $page->items
            ->map(fn (ImageSearchItem $item): ?ImageItemData => $item->data->first())
            ->filter()
            ->values()
            ->all();
        $this->results->setRows(array_map(fn (ImageItemData $data): array => [
            'date' => substr($data->dateCreated ?? '', 0, 10),
            'center' => $data->center ?? '',
            'title' => $data->title ?? $data->nasaId,
        ], $this->items));

        $this->items === []
            ? $this->empty($bag, "No {$type->value} matches “{$query}”.")
            : $this->done($bag, sprintf('%d of %s %s results for “%s”.', count($this->items), number_format($page->totalHits ?? count($this->items)), $type->value, $query));
    }

    /**
     * The picked item's title, provenance and description below the viewer.
     */
    public function describe(ImageItemData $item): void
    {
        $this->current = $item;
        $this->description->setValue(
            ($item->title ?? $item->nasaId)."\n"
            .implode(' · ', array_filter([$item->dateCreated, $item->center, $item->photographer]))."\n\n"
            .self::plain($item->description)
        );
    }

    /**
     * The manifest file to download for a kind of media, or null when the item has none.
     * @param SharedBag $bag Carries 'hrefs', the open item's manifest files.
     */
    public function source(SharedBag $bag, ImageMediaType $type): ?MediaSource
    {
        $href = MediaVariant::pick($bag->hrefs, $type);

        return is_null($href) ? null : MediaSource::of($href);
    }

    /**
     * @param list<string> $hrefs The item's asset manifest files: where a sharper cut comes from.
     */
    public function showPicture(string $file, array $hrefs = []): void
    {
        $this->stopVideo();
        $this->link->hide();
        $this->picture_menu->clear();
        $this->hrefs = $hrefs;
        if (! $this->picture->show($file)) {
            $this->showLink('This picture is too large to show in the memory PHP has.');

            return;
        }
        if (! is_null($this->current)) {
            $this->picture_menu->target($this->picture->view(), $file, $this->current->title ?? $this->current->nasaId, self::detailsUrl($this->current));
        }
    }

    /** An item's page on images.nasa.gov, for Copy Link. */
    protected static function detailsUrl(ImageItemData $item): string
    {
        return 'https://images.nasa.gov/details/'.rawurlencode($item->nasaId);
    }

    /** The shown picture, which sees view mail before the page does. */
    public function viewer(): PictureViewer
    {
        return $this->picture;
    }

    /**
     * Zoomed past the shown cut's pixels: the item's bigger cuts, biggest first, until one fits in
     * memory and takes its place.
     */
    public function sharpenPicture(string $file): void
    {
        $item = $this->current;
        $shown = MediaVariant::pick($this->hrefs, ImageMediaType::IMAGE);
        $tries = is_null($shown) ? [] : MediaVariant::sharper($this->hrefs, $shown);
        if ($tries === [] || is_null($item)) {
            return;
        }

        $status = GlobalVariables::make()->status_bar;
        $status?->busy($this->key(), 'Fetching a bigger cut to zoom into…');
        $this->tryCut($item, $tries, $status);
    }

    /**
     * @param list<string> $tries
     */
    protected function tryCut(ImageItemData $item, array $tries, ?StatusBar $status): void
    {
        $href = array_shift($tries);
        $name = $item->title ?? $item->nasaId;
        $next = fn () => $tries === []
            ? $status?->settle($this->key(), Outcome::DONE, "No bigger cut of “{$name}” fits in the memory PHP has; zooming the one shown.")
            : $this->tryCut($item, $tries, $status);

        $this->media->fetch(MediaSource::of($href))
            ->then(function (string $path) use ($item, $name, $status, $next): void {
                if ($this->current !== $item) {
                    return;
                }
                if (! $this->picture->sharpen($path)) {
                    $next();

                    return;
                }
                $this->picture_menu->clear();
                $this->picture_menu->target($this->picture->view(), $path, $name, self::detailsUrl($item));
                $size = @getimagesize($path) ?: [0, 0];
                $status?->settle($this->key(), Outcome::DONE, sprintf('%s · %d × %d', $name, $size[0], $size[1]));
            })
            ->error(fn () => $this->current === $item ? $next() : null);
    }

    /**
     * A choice from the shown picture's context menu.
     */
    public function menuActivated(MenuActivated $mail): bool
    {
        return $this->picture_menu->activated($mail);
    }

    public function showVideo(string $file): void
    {
        $this->picture_menu->clear();
        $this->picture->hide();
        $this->link->hide();
        $this->ranged = false;
        $this->seek->setRange(0, 1)->setValue(0);
        $this->video->setMuted($this->mute->isChecked());
        $this->video->setLoop(self::isSwitchedOn($this->loop));
        $this->video->setFile($file)->show();
        $this->controls->show();
        $this->video->play();
    }

    public function showLink(string $text): void
    {
        $this->picture_menu->clear();
        $this->stopVideo();
        $this->picture->hide();
        $this->link->setText($text)->show();
    }

    public function togglePlay(bool $on): bool
    {
        $on ? $this->video?->play() : $this->video?->pause();

        return true;
    }

    public function setMuted(bool $muted): bool
    {
        $this->video?->setMuted($muted);

        return true;
    }

    public function setLoop(bool $loop): bool
    {
        $this->video?->setLoop($loop);

        return true;
    }

    public function seek(float $seconds): bool
    {
        $this->video?->seek($seconds);

        return true;
    }

    /**
     * The video's own state changed: the play button follows it.
     */
    public function playing(bool $playing): bool
    {
        $this->play->setPressed($playing)->setLabel($playing ? 'Pause' : 'Play');

        return true;
    }

    public function videoFailed(string $reason): bool
    {
        $this->showLink("The video would not play ({$reason}).");

        return true;
    }

    /**
     * While a video plays, the seek bar follows it. Setting the bar from here posts nothing,
     * so it never seeks the video back.
     */
    public function tick(): void
    {
        if (is_null($this->video) || ! $this->video->isPlaying() || microtime(true) - $this->seeked_at < self::SEEK_EVERY) {
            return;
        }

        $this->seeked_at = microtime(true);
        if (! $this->ranged && ! is_null($duration = $this->video->duration()) && $duration > 0) {
            $this->seek->setRange(0, $duration);
            $this->ranged = true;
        }
        $this->seek->setValue($this->video->position());
    }

    protected function stopVideo(): void
    {
        if (! is_null($this->video) && $this->video->isVisible()) {
            $this->video->pause()->hide();
        }
        $this->controls->hide();
    }

    protected function setBusy(bool $busy): void
    {
        $this->search->setEnabled(! $busy);
        $this->results->setEnabled(! $busy);
    }
}
