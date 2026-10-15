<?php

namespace App\Workflows;

use App\Enums\Outcome;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

/**
 * Where failure routes end: records the outcome the status row shows.
 */
class Report extends Node
{
    public function __construct(protected Outcome $outcome)
    {
        parent::__construct();
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        $message = $shared->error ?? $this->outcome->defaultMessage();

        $shared->outcome ??= [$this->outcome, $message];

        return null;
    }
}
