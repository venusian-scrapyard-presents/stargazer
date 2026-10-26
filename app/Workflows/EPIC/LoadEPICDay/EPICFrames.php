<?php

namespace App\Workflows\EPIC\LoadEPICDay;

use App\Concerns\FollowsRun;
use App\NutsAndBolts\FetchFailure;
use App\NutsAndBolts\MediaCache;
use App\NutsAndBolts\MediaSource;
use Closure;
use ProjectSaturnStudios\Stargazer\EPIC\DataObjects\EpicImage;
use ProjectSaturnStudios\Stargazer\EPIC\Enums\EpicCollection;
use ProjectSaturnStudios\Stargazer\EPIC\Enums\EpicImageType;
use Throwable;
use Voyager\Workflows\AsyncParallelBatchNode;
use Voyager\Workflows\SharedBag;

/**
 * A day of EPIC frames, four downloads in flight, each retried on its own. The progress bar
 * moves as each settles; a frame that still fails is dropped and the day plays without it.
 * Reads 'epic_images'; writes 'epic_frames' as [file, record, collection] in the day's order.
 */
class EPICFrames extends AsyncParallelBatchNode
{
    use FollowsRun;

    protected int $settled = 0;

    protected int $total = 0;

    /**
     * @param MediaCache $media
     * @param EpicCollection $collection
     * @param Closure $progress (int $settled, int $total): void — called while the run is current.
     */
    public function __construct(
        protected MediaCache $media,
        protected EpicCollection $collection,
        protected Closure $progress,
    ) {
        parent::__construct(maxRetries: 3, wait: 2, concurrency: 4);
    }

    /**
     * @param SharedBag $shared
     * @return list<EpicImage>
     */
    public function prepAsync(SharedBag $shared): mixed
    {
        $this->settled = 0;
        $this->total = count($shared->epic_images);

        return $shared->epic_images;
    }

    /**
     * @param EpicImage $prepRes
     * @return mixed
     */
    public function execAsync(mixed $prepRes): mixed
    {
        $source = new MediaSource(
            $prepRes->archiveUrl($this->collection, EpicImageType::JPG),
            fn () => $prepRes->render($this->collection, EpicImageType::JPG),
        );

        // A stale day downloads nothing more; a missing frame is not asked for again.
        if ($this->stale()) {
            return null;
        }

        return FetchFailure::finalOn4xx($this->media->fetch($source), $source->url)
            ->then(function (string|FetchFailure $result): ?string {
                $this->advance();

                return is_string($result) ? $result : null;
            });
    }

    public function execFallbackAsync(mixed $prepRes, Throwable $e): mixed
    {
        $this->advance();

        return null;
    }

    public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
    {
        // Keep each frame beside its image record and the collection it came from, in the day's order.
        $shared->epic_frames = [];
        foreach ($execRes ?? [] as $i => $path) {
            if (! is_null($path)) {
                $shared->epic_frames[] = [$path, $shared->epic_images[$i], $this->collection];
            }
        }

        if ($this->stale()) {
            return 'stale';
        }

        if ($shared->epic_frames === []) {
            $shared->error = 'No EPIC frame downloaded.';

            return 'failed';
        }

        return null;
    }

    protected function advance(): void
    {
        $this->settled++;
        if ($this->current()) {
            ($this->progress)($this->settled, $this->total);
        }
    }
}
