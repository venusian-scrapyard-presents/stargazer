<?php

namespace App\Workflows;

use App\Concerns\FollowsRun;
use App\NutsAndBolts\FetchFailure;
use App\NutsAndBolts\MediaSource;
use Closure;
use Throwable;
use Voyager\Workflows\AsyncNode;
use App\NutsAndBolts\MediaCache;
use Voyager\Workflows\SharedBag;

/**
 * Downloads one picture or video into the media cache; the local path lands on the bag.
 * Retries like Fetch (a 4xx is final at once); a failure routes 'failed' or 'rate_limited',
 * a stale run downloads nothing and routes 'stale'.
 */
class MediaDownload extends AsyncNode
{
    use FollowsRun;

    /**
     * @param MediaCache $media
     * @param Closure $source (SharedBag): ?MediaSource — null skips the download.
     * @param string $into The bag key the local path lands on (null when skipped).
     */
    public function __construct(
        protected MediaCache $media,
        protected Closure $source,
        protected string $into,
        int $retries = 3,
        int $wait = 2,
    ) {
        parent::__construct($retries, $wait);
    }

    /**
     * @param SharedBag $shared
     * @return MediaSource|false|null false for a stale run.
     */
    public function prepAsync(SharedBag $shared): mixed
    {
        return $this->stale() ? false : ($this->source)($shared);
    }

    /**
     * @param MediaSource|false|null $prepRes
     * @return mixed
     */
    public function execAsync(mixed $prepRes): mixed
    {
        if (! $prepRes instanceof MediaSource || $this->stale()) {
            return null;
        }

        return FetchFailure::finalOn4xx($this->media->fetch($prepRes), $prepRes->url);
    }

    public function execFallbackAsync(mixed $prepRes, Throwable $e): mixed
    {
        return FetchFailure::from($e, $prepRes instanceof MediaSource ? $prepRes->url : null);
    }

    public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
    {
        if ($prepRes === false || $this->stale()) {
            return 'stale';
        }

        if ($execRes instanceof FetchFailure) {
            $shared->error = $execRes->message();
            $shared->failure = $execRes;

            return $execRes->route();
        }

        $shared->put($this->into, $execRes);

        return null;
    }
}
