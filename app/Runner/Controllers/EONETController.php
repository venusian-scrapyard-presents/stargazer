<?php

namespace App\Runner\Controllers;

use App\GlobalVariables;
use App\Services\EONET\EONETService;
use App\Workflows\EONET\LoadEONETEvents\LoadEONETEventsStart;
use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\RowSelected;
use Surface\Contracts\Windows\Mail\View\CanvasClicked;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\ToolkitWindow;
use Voyager\Contracts\Signals\NamedSignal;
use Voyager\Workflows\Flow;

class EONETController extends StargazerController
{
    public function __construct(
        ToolkitWindow $window,
        public readonly EONETService $service
    )
    {
        parent::__construct($window);
    }

    public function title(): string
    {
        return 'Natural Events';
    }

    public function key(): string
    {
        return 'eonet';
    }

    public function menu(): string
    {
        return 'main';
    }

    public function refresh(): void
    {
        $start_node = new LoadEONETEventsStart($this->service);
        new Flow($start_node)->run(GlobalVariables::make());
    }

    public function index(TKColumn $root): void
    {
        $this->service->mounted($root);
    }

    public function deliver(NamedSignal $mail): bool
    {
        $service = $this->service;

        return match (true) {
            $mail instanceof ButtonClicked && $service->isLoad($mail->uuid()) => $this->reload(),
            $mail instanceof CanvasClicked && $service->isMap($mail->uuid()) => $service->pickAt($mail->x, $mail->y),
            $mail instanceof RowSelected && $service->isEventsTable($mail->uuid()) => $service->choose($mail->row, from_table: true),
            default => false,
        };
    }

    /**
     * Every frame: the map follows its canvas and shows what changed.
     */
    public function tick(): void
    {
        $this->service->tick();
    }

    protected function reload(): bool
    {
        $this->refresh();

        return true;
    }
}
