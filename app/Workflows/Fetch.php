<?php

namespace App\Workflows;

use App\Concerns\FollowsRun;
use App\NutsAndBolts\FetchFailure;
use Closure;
use Throwable;
use Voyager\Workflows\AsyncNode;
use Voyager\Workflows\SharedBag;

class Fetch extends AsyncNode
{
    use FollowsRun;

    public function __construct(
        protected Closure $request,
        protected string|Closure $into,
        int $retries = 3,
        int $wait = 2,
    ) {
        parent::__construct($retries, $wait);
    }
    /**
     * Read workflow state and prepare input for execAsync().
     */
    public function prepAsync(SharedBag $shared): mixed
    {
        return !$this->stale() ? ($this->request)($shared, $this->params) : null;
    }

    /**
     * Perform the node's work through the configured async runtime.
     */
    public function execAsync(mixed $prepRes): mixed
    {
        if (is_null($prepRes) || $this->stale()) {
            return null;
        }

        return FetchFailure::finalOn4xx($prepRes->async(), $prepRes->url());
    }

    public function execFallbackAsync(mixed $prepRes, Throwable $e): mixed
    {
        return FetchFailure::from($e, $prepRes?->url());
    }


    /**
     * Write results into shared state and return the next action.
     */
    public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
    {
        if (is_null($prepRes) || $this->stale()) {
            return 'stale';
        }

        if ($execRes instanceof FetchFailure) {
            $shared->error = $execRes->message();
            $shared->failure = $execRes;

            return $execRes->route();
        }

        $shared->put(is_string($this->into) ? $this->into : ($this->into)($this->params), $execRes);

        return null;
    }
}
