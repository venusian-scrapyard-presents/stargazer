<?php

namespace App\Services\EPIC;

use App\Concerns\EPIC\EPICContent;
use App\GlobalVariables;
use App\NutsAndBolts\PictureViewer;
use App\Services\NasaService;
use DateTimeImmutable;
use ProjectSaturnStudios\Stargazer\EPIC\DataObjects\EpicImage;
use ProjectSaturnStudios\Stargazer\EPIC\Enums\EpicCollection;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Voyager\NutsAndBolts\Collection;

/**
 * DSCOVR's EPIC camera: a day of whole-Earth frames, downloaded side by side, scrubbed with a
 * slider so the planet turns.
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

    public function isScrub(string $uuid): bool
    {
        return $uuid === $this->scrub?->uuid();
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
        $this->scrub->disable();
        $this->progress->setFraction(0.0)->show();

        return true;
    }

    public function progressed(int $settled, int $total): void
    {
        $this->progress->setFraction($total === 0 ? 1.0 : $settled / $total);
    }

    /**
     * The day's frames downloaded: the slider spans them, the first is shown, and the picker
     * moves to the day EPIC answered with.
     */
    public function framesLoaded(GlobalVariables $bag): void
    {
        $this->loaded = true;
        $this->frames = $bag->epic_frames;
        $this->progress->hide();
        $this->scrub->setRange(0, max(1, count($this->frames) - 1))->setValue(0)->setEnabled(count($this->frames) > 1);
        $this->showFrame(0);

        $day = substr($this->frames[0][1]->date, 0, 10);
        $this->date->setDate(new DateTimeImmutable($day));
        $missing = count($bag->epic_images) - count($this->frames);
        $this->done($bag, sprintf('%d frames of %s%s — drag the slider to turn the Earth.', count($this->frames), $day, $missing > 0 ? " ({$missing} would not download)" : ''));
    }

    public function scrubTo(float $value): bool
    {
        $this->showFrame((int) round($value));

        return true;
    }

    protected function showFrame(int $index): void
    {
        [$file, $image] = $this->frames[$index] ?? [null, null];
        if (is_null($file)) {
            return;
        }

        $this->frame->show($file, keep_view: true);
        $this->caption->setText(sprintf(
            '%s UTC · centred on %.1f°, %.1f° · frame %d of %d',
            $image->date,
            $image->centroid->lat,
            $image->centroid->lon,
            $index + 1,
            count($this->frames),
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
