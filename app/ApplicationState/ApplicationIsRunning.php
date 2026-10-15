<?php

namespace App\ApplicationState;

use App\GlobalVariables;

final readonly class ApplicationIsRunning extends ApplicationState
{
    public function __construct()
    {
        parent::__construct("RUNNING");
    }

    public function nextStatus(GlobalVariables $bag): ApplicationState
    {
        $results = $this;

        if($bag->ending)
        {
            $results = new ApplicationIsTerminating();
        }

        return $results;
    }
}
