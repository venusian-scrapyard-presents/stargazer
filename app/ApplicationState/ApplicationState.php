<?php

namespace App\ApplicationState;

use App\GlobalVariables;

abstract readonly class ApplicationState
{
    public string $status;

    public function __construct(string $s) {
        $this->status = $s;
    }

    abstract public function nextStatus(GlobalVariables $bag): ApplicationState;

    public function isBooted(): bool
    {
        return true;
    }
}
