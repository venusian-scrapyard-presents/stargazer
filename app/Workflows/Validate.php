<?php

namespace App\Workflows;

use Closure;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

/**
 * Checks a condition before anything is sent. A problem lands on the bag as 'error' and routes
 * 'invalid'.
 */
class Validate extends Node
{
    /**
     * @param Closure $check (SharedBag): ?string — the problem, or null when all is well.
     */
    public function __construct(protected Closure $check)
    {
        parent::__construct();
    }

    public function prep(SharedBag $shared): mixed
    {
        return ($this->check)($shared);
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        if (is_null($prepRes)) {
            return null;
        }

        $shared->error = $prepRes;

        return 'invalid';
    }
}
