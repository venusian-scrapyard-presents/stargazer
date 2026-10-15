<?php

namespace App\Concerns;

/**
 * The run counter behind FlowOwner::nextRun() / isCurrent().
 */
trait TracksRuns
{
    protected int $run = 0;

    public function nextRun(): int
    {
        return ++$this->run;
    }

    public function isCurrent(int $run): bool
    {
        return $run === $this->run;
    }
}
