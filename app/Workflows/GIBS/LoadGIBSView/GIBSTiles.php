<?php

namespace App\Workflows\GIBS\LoadGIBSView;

use App\Concerns\FollowsRun;
use App\NutsAndBolts\FetchFailure;
use Closure;
use ProjectSaturnStudios\Stargazer\PendingNasaRequest;
use Throwable;
use Voyager\Workflows\AsyncParallelBatchNode;
use Voyager\Workflows\SharedBag;

/**
 * The tiles a view needs ('gibs_tiles'), six in flight. Each one is handed over the moment it
 * arrives, so the map fills in tile by tile; one that still fails after its retries is left blank.
 * How many arrived lands as 'gibs_loaded'.
 */
class GIBSTiles extends AsyncParallelBatchNode
{
    use FollowsRun;

    /**
     * @param Closure $request (array $tile): PendingNasaRequest
     * @param Closure $arrived (array $tile, mixed $result): void — what the request answered; called while the run is current.
     */
    public function __construct(
        protected Closure $request,
        protected Closure $arrived,
    ) {
        parent::__construct(maxRetries: 2, wait: 1, concurrency: 6);
    }

    /** @return list<array<string, mixed>> */
    public function prepAsync(SharedBag $shared): mixed
    {
        return $shared->gibs_tiles;
    }

    public function execAsync(mixed $prepRes): mixed
    {
        if ($this->stale()) {
            return null;
        }

        /** @var PendingNasaRequest $request */
        $request = ($this->request)($prepRes);

        return FetchFailure::finalOn4xx($request->async(), $request->url())
            ->then(function (mixed $result) use ($prepRes): bool {
                if ($result instanceof FetchFailure || ! $this->current()) {
                    return false;
                }
                ($this->arrived)($prepRes, $result);

                return true;
            });
    }

    public function execFallbackAsync(mixed $prepRes, Throwable $e): mixed
    {
        return false;
    }

    public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
    {
        $shared->gibs_loaded = count(array_filter($execRes ?? []));

        return $this->stale() ? 'stale' : null;
    }
}
