<?php

namespace App\Services\EPIC;

use App\Concerns\EPIC\EPICContent;
use App\GlobalVariables;
use App\NutsAndBolts\FramePlayer;
use App\NutsAndBolts\PictureViewer;
use ProjectSaturnStudios\Stargazer\EPIC\Enums\EpicImageType;
use Surface\Contracts\Windows\Mail\MenuActivated;
use App\Services\NasaService;
use DateTimeImmutable;
use ProjectSaturnStudios\Stargazer\EPIC\DataObjects\EpicImage;
use ProjectSaturnStudios\Stargazer\EPIC\Enums\EpicCollection;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Voyager\NutsAndBolts\Collection;

/**
 * DSCOVR's EPIC camera: a day of whole-Earth frames, downloaded side by side, played in a loop
 * so the planet turns.
 */
class EPICService extends NasaService
{
    use EPICContent;

    protected const array COLLECTIONS = ['Natural' => EpicCollection::NATURAL, 'Enhanced' => EpicCollection::ENHANCED];

    /** @var list<array{0: string, 1: EpicImage}> [file, record] per downloaded frame. */
    protected array $frames = [];

    /** The first load asks for the latest day; later ones use the picker. */
    protected bool $loaded = false;

    public function key(): string
    {
        return 'epic';
    }

    public function mounted(TKColumn $root): void
    {
        $this->setMainComponents($root);
    }

    public function collection(): EpicCollection
    {
        return array_values(self::COLLECTIONS)[$this->collection->selected()];
    }

    /**
     * The picker's day, as Y-m-d; null when it has none.
     */
    public function pickedDate(): ?string
    {
        return $this->date->date()?->format('Y-m-d');
    }

    /**
     * Until a day has loaded, null (EPIC's latest day); after that, the picker's.
     */
    public function refreshDate(): ?string
    {
        return $this->loaded ? $this->pickedDate() : null;
    }

    /** The frame shown, which sees view mail before the page does. */
    public function viewer(): PictureViewer
    {
        return $this->frame;
    }

    public function isLoad(string $uuid): bool
    {
        return $uuid === $this->load?->uuid();
    }

    /** Whether $uuid is one of the player's buttons. */
    public function isPlayer(string $uuid): bool
    {
        return in_array($uuid, [$this->previous?->uuid(), $this->play?->uuid(), $this->next?->uuid()], true);
    }

    /**
     * The day's image records landed: none ends the run as empty; some reset the viewer for
     * their downloads.
     * @param string|null $date What was asked for; null for the latest day.
     * @return bool Whether there is anything to download.
     */
    public function listed(GlobalVariables $bag, ?string $date): bool
    {
        /** @var Collection $images */
        $images = $bag->epic_images;
        $bag->epic_images = $images->values()->all();

        if ($bag->epic_images === []) {
            $this->empty($bag, 'No EPIC frames for '.($date ?? 'the latest day').'.');

            return false;
        }

        $this->frames = [];
        $this->player->load(0, self::now());
        $this->paintPlayer();
        $this->progress->setFraction(0.0)->show();

        return true;
    }

    public function progressed(int $settled, int $total): void
    {
        $this->progress->setFraction($total === 0 ? 1.0 : $settled / $total);
    }

    /**
     * The day's frames downloaded: the first is shown and the day plays, and the picker moves
     * to the day EPIC answered with.
     */
    public function framesLoaded(GlobalVariables $bag): void
    {
        $this->loaded = true;
        $this->frames = $bag->epic_frames;
        $this->progress->hide();
        $this->player->load(count($this->frames), self::now());
        $this->showFrame(0);
        $this->paintPlayer();

        $day = substr($this->frames[0][1]->date, 0, 10);
        $this->date->setDate(new DateTimeImmutable($day));
        $missing = count($bag->epic_images) - count($this->frames);
        $this->done($bag, sprintf('%d frames of %s%s — Pause holds the Earth still, Prev and Next step it.', count($this->frames), $day, $missing > 0 ? " ({$missing} would not download)" : ''));
    }

    /** A choice from the shown frame's context menu. */
    public function menuActivated(MenuActivated $mail): bool
    {
        return $this->frame_menu->activated($mail);
    }

    public function isSpeed(string $uuid): bool
    {
        return $uuid === $this->speed?->uuid();
    }

    /** A pick from the speed menu: index $index of FramePlayer::SPEEDS. */
    public function speedChosen(int $index): bool
    {
        $seconds = array_values(FramePlayer::SPEEDS)[$index] ?? FramePlayer::STEP;
        $this->player->setStep($seconds);

        return true;
    }

    /** A click on one of the player's buttons. */
    public function pressed(string $uuid): bool
    {
        match ($uuid) {
            $this->previous->uuid() => $this->showFrame($this->player->previous()),
            $this->next->uuid() => $this->showFrame($this->player->next()),
            default => $this->player->toggle(self::now()),
        };
        $this->paintPlayer();

        return true;
    }

    /** Every frame: the next picture of the day once it is due. */
    public function play(): void
    {
        $index = $this->player->advance(self::now());
        if (! is_null($index)) {
            $this->showFrame($index);
            $this->paintPlayer();
        }
    }

    /** The screen went away: the Earth stops where it is. */
    public function pause(): void
    {
        if (is_null($this->player)) {
            return;
        }

        $this->player->pause();
        $this->paintPlayer();
    }

    protected function paintPlayer(): void
    {
        $this->setPlayerEnabled($this->player->canStep());
        $this->play->setLabel($this->player->isPlaying() ? '❚❚ Pause' : '▶ Play');
        $this->position->setText($this->player->label());
    }

    protected static function now(): float
    {
        return hrtime(true) / 1e9;
    }

    protected function showFrame(int $index): void
    {
        [$file, $image, $collection] = $this->frames[$index] ?? [null, null, null];
        if (is_null($file)) {
            return;
        }

        $this->frame->show($file, keep_view: true);
        $this->frame_menu->clear();
        $this->frame_menu->target($this->frame->view(), $file, 'EPIC '.$image->date, $image->archiveUrl($collection, EpicImageType::JPG));
        $this->caption->setText(sprintf(
            '%s UTC · centred on %.1f°, %.1f°',
            $image->date,
            $image->centroid->lat,
            $image->centroid->lon,
        ));
    }

    protected function setBusy(bool $busy): void
    {
        $this->load->setEnabled(! $busy);
        if (! $busy) {
            $this->progress->hide();
        }
    }
}
