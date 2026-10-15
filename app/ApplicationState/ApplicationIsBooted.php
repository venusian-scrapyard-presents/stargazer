<?php

namespace App\ApplicationState;

use App\GlobalVariables;

final readonly class ApplicationIsBooted extends ApplicationState
{
    public function __construct()
    {
        parent::__construct("BOOTED");
    }

    public function nextStatus(GlobalVariables $bag): ApplicationState
    {
        $results = $this;
        $resolution_set = ($bag->main_window_width > 0) && ($bag->main_window_height > 0);
        $window_provisioned = !is_null($bag->window);

        if($resolution_set && $window_provisioned)
        {
            $results = new ApplicationIsRunning();
        }

        return $results;
    }
}
