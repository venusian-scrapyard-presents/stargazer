<?php

namespace App\NutsAndBolts;

use Surface\Contracts\Windows\Primitives\TKProgressBar;
use Surface\Contracts\Windows\Primitives\TKSpinner;

/**
 * The status row's activity indicator: a spinner, or an indeterminate bar on a toolkit
 * without one.
 */
final class Busy
{
    public function __construct(protected TKSpinner|TKProgressBar $indicator)
    {
        $this->set(false);
    }

    public function set(bool $busy): void
    {
        if ($this->indicator instanceof TKSpinner) {
            $busy ? $this->indicator->start() : $this->indicator->stop();
        } else {
            $this->indicator->setFraction(null);
        }

        $this->indicator->setVisible($busy);
    }
}
