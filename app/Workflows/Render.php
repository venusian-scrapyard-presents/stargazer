<?php

namespace App\Workflows;

use App\Services\NasaService;
use Closure;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

/**
 * Writes a run's results into the primitives — unless a newer run has started, in which case
 * it routes 'stale' and paints nothing.
 */
class Render extends Node
{
    /**
     * @param FlowOwner $owner
     * @param Closure $body (SharedBag, array $params): ?string — the action to follow.
     */
    public function __construct(
        protected NasaService $owner,
        protected Closure $body,
    ) {
        parent::__construct();
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        if (! $this->owner->isCurrent((int) ($this->params['run'] ?? 0))) {
            return 'stale';
        }

        return ($this->body)($shared, $this->params);
    }
}
