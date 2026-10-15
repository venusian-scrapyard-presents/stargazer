<?php

namespace App\Runner\Controllers;

use App\GlobalVariables;
use App\Services\EPIC\EPICService;
use App\Workflows\EPIC\LoadEPICDay\LoadEPICDayStart;
use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\ValueChanged;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\ToolkitWindow;
use Voyager\Contracts\Signals\NamedSignal;
use Voyager\Workflows\Flow;

class EPICController extends StargazerController
{
    public function __construct(
        ToolkitWindow $window,
        public readonly EPICService $service
    )
    {
        parent::__construct($window);
    }

    public function title(): string
    {
        return 'Earth from DSCOVR';
    }

    public function key(): string
    {
        return 'epic';
    }

    public function menu(): string
    {
        return 'main';
    }

    /**
     * Until a day has loaded, the latest day; after that, the picker's.
     */
    public function refresh(): void
    {
        $this->load($this->service->refreshDate());
    }

    public function index(TKColumn $root): void
    {
        $this->service->mounted($root);
    }

    public function deliver(NamedSignal $mail): bool
    {
        // The frame zooms on its own clicks and buttons.
        if ($this->service->viewer()->handles($mail)) {
            return true;
        }

        return match (true) {
            // The Load button always asks for the day the picker shows.
            $mail instanceof ButtonClicked && $this->service->isLoad($mail->uuid()) => $this->load($this->service->pickedDate()),
            $mail instanceof ValueChanged && $this->service->isScrub($mail->uuid()) => $this->service->scrubTo($mail->value),
            default => false,
        };
    }

    /**
     * Every frame: the shown frame follows its canvas and shows what changed.
     */
    public function tick(): void
    {
        $this->service->viewer()->tick();
    }

    protected function load(?string $date): bool
    {
        $start_node = new LoadEPICDayStart($this->service, $date);
        new Flow($start_node)->run(GlobalVariables::make());

        return true;
    }
}
