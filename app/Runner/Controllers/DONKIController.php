<?php

namespace App\Runner\Controllers;

use App\GlobalVariables;
use App\Services\DONKI\DONKIService;
use App\Workflows\DONKI\LoadDONKIEvents\LoadDONKIEventsStart;
use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\CanvasClicked;
use Surface\Contracts\Windows\Mail\View\RowSelected;
use Surface\Contracts\Windows\Mail\View\Toggled;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\ToolkitWindow;
use Voyager\Contracts\Signals\NamedSignal;
use Voyager\Workflows\Flow;

class DONKIController extends StargazerController
{
    public function __construct(
        ToolkitWindow $window,
        public readonly DONKIService $service
    )
    {
        parent::__construct($window);
    }

    public function title(): string
    {
        return 'Space Weather';
    }

    public function key(): string
    {
        return 'donki';
    }

    public function menu(): string
    {
        return 'main';
    }

    public function refresh(): void
    {
        $start_node = new LoadDONKIEventsStart($this->service);
        new Flow($start_node)->run(GlobalVariables::make());
    }

    public function index(TKColumn $root): void
    {
        $this->service->mounted($root);
    }

    public function deliver(NamedSignal $mail): bool
    {
        return match (true) {
            $mail instanceof ButtonClicked && $this->service->isLoad($mail->uuid()) => $this->reload(),
            $mail instanceof RowSelected && $this->service->isEventsTable($mail->uuid()) => $this->service->showDetail($mail->row),
            $mail instanceof CanvasClicked && $this->service->isTimeline($mail->uuid()) => $this->service->pickAt($mail->x, $mail->y),
            $mail instanceof Toggled && $this->service->isList($mail->uuid()) => $this->service->showList($mail->on),
            default => false,
        };
    }

    /**
     * Every frame: the timeline follows its canvas and shows what changed.
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
