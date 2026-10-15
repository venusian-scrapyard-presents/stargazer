<?php

namespace App\ApplicationState;

use App\GlobalVariables;

final readonly class ApplicationIsTerminating extends ApplicationState
{
    public function __construct(

    )
    {
        parent::__construct("TERMINATING");
    }

    public function isBooted(): bool
    {
        return false;
    }

    public function nextStatus(GlobalVariables $bag): ApplicationState
    {
        return $this;
    }
}
