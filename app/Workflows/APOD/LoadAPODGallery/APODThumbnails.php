<?php

namespace App\Workflows\APOD\LoadAPODGallery;

use Closure;
use Throwable;
use App\Concerns\FollowsRun;
use App\NutsAndBolts\MediaCache;
use App\NutsAndBolts\MediaSource;
use App\NutsAndBolts\FetchFailure;
use Voyager\Workflows\SharedBag;
use Voyager\Workflows\AsyncParallelBatchNode;

/**
 * The gallery's pictures, four downloads in flight, each retried on its own. Every cell gets
 * its picture the moment its file lands; a download that still fails leaves that cell's
 * caption saying so instead of failing the gallery.
 */
class APODThumbnails extends AsyncParallelBatchNode
{
    use FollowsRun;

    /**
     * @param MediaCache $media
     * @param Closure $landed (int $index, ?string $path): void — called per cell while the run is current.
     */
    public function __construct(
        protected MediaCache $media,
        protected Closure $landed,
    ) {
        parent::__construct(maxRetries: 3, wait: 2, concurrency: 4);
    }

    /**
     * @param SharedBag $shared
     * @return array<int, array{0: int, 1: MediaSource}>
     */
    public function prepAsync(SharedBag $shared): mixed
    {
        return $shared->sources;
    }

    /**
     * @param array{0: int, 1: MediaSource} $prepRes
     * @return mixed
     */
    public function execAsync(mixed $prepRes): mixed
    {
        [$index, $source] = $prepRes;

        // A stale gallery downloads nothing more; a missing picture is not asked for again.
        if ($this->stale()) {
            return null;
        }

        return FetchFailure::finalOn4xx($this->media->fetch($source), $source->url)
            ->then(function (string|FetchFailure $result) use ($index): ?string {
                $path = is_string($result) ? $result : null;
                $this->deliver($index, $path);

                return $path;
            });
    }

    public function execFallbackAsync(mixed $prepRes, Throwable $e): mixed
    {
        $this->deliver($prepRes[0], null);

        return null;
    }

    public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
    {
        $shared->thumbnails = array_filter($execRes ?? []);

        return null;
    }

    protected function deliver(int $index, ?string $path): void
    {
        if ($this->current()) {
            ($this->landed)($index, $path);
        }
    }
}
