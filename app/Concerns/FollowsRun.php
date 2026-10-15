<?php

namespace App\Concerns;

use App\Services\NasaService;

/**
 * For nodes that send requests: whether the run they belong to is still the owner's current
 * one. A stale run sends nothing more. FlowRunner puts 'run' and 'owner' in the flow's params.
 */
trait FollowsRun
{
    protected function stale(): bool
    {
        $owner = $this->params['owner'] ?? null;

        return $owner instanceof NasaService && ! $owner->isCurrent((int) ($this->params['run'] ?? 0));
    }

    protected function current(): bool
    {
        return ! $this->stale();
    }
}
