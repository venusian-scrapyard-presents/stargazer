<?php

namespace App\Workflows\APOD\LoadAPODDay;

use App\Concerns\FollowsRun;
use App\Models\ApodDay;
use Throwable;
use Voyager\Workflows\AsyncNode;
use Voyager\Workflows\SharedBag;

/**
 * Looks the day up in apod_days, read in a pool worker. A stored day lands on the bag as
 * 'picture' and routes 'stored'; a missing one (or a failed read) routes 'fetch'.
 */
class RecallAPODDay extends AsyncNode
{
    use FollowsRun;

    public function __construct(
        protected string $date
    ) {
        parent::__construct();
    }

    public function execAsync(mixed $prepRes): mixed
    {
        return $this->stale() ? null : ApodDay::query()->where('date', $this->date)->via()->first();
    }

    public function execFallbackAsync(mixed $prepRes, Throwable $e): mixed
    {
        return null;
    }

    public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
    {
        if ($this->stale()) {
            return 'stale';
        }

        if ($execRes instanceof ApodDay) {
            $shared->picture = $execRes->toPicture();

            return 'stored';
        }

        return 'fetch';
    }
}
