<?php

namespace App\ApplicationState;

use App\GlobalVariables;

final readonly class ApplicationIsNotRunning extends ApplicationState
{
    public function __construct(

    )
    {
        parent::__construct("NOT_RUNNING");
    }

    public function isBooted(): bool
    {
        return false;
    }

    public function nextStatus(GlobalVariables $bag): ApplicationState
    {
        $results = $this;
        $loop_instanced = !is_null($bag->event_loop);
        $session_started = !is_null($bag->session);

        if($loop_instanced && $session_started)
        {
            $results = new ApplicationIsBooted();
        }

        return $results;
    }
}
