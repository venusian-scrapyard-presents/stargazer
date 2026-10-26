<?php

namespace App\Runner\Controllers;

use App\Services\GIBS\GIBSService;
use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\DateChanged;
use Surface\Contracts\Windows\Mail\View\PrimitiveMail;
use Surface\Contracts\Windows\Mail\View\SelectionChanged;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\ToolkitWindow;
use Voyager\Contracts\Signals\NamedSignal;

class GIBSController extends StargazerController
{
    public function __construct(
        ToolkitWindow $window,
        public readonly GIBSService $service
    )
    {
        parent::__construct($window);
    }

    public function title(): string
    {
        return 'Earth, live (GIBS)';
    }

    public function key(): string
    {
        return 'gibs';
    }

    public function menu(): string
    {
        return 'main';
    }

    public function refresh(): void
    {
        $this->service->refresh();
    }

    public function index(TKColumn $root): void
    {
        $this->service->mounted($root);
    }

    public function deliver(NamedSignal $mail): bool
    {
        $service = $this->service;

        return match (true) {
            $mail instanceof PrimitiveMail && $service->isMap($mail->uuid()) => $service->pointer($mail),
            $mail instanceof SelectionChanged && $service->isProjection($mail->uuid()) => $service->changed('projection'),
            $mail instanceof SelectionChanged && $service->isLayer($mail->uuid()) => $service->changed('layer'),
            $mail instanceof SelectionChanged && $service->isOverlay($mail->uuid()) => $service->changed('overlay'),
            $mail instanceof DateChanged && $service->isDate($mail->uuid()) => $service->changed('date'),
            $mail instanceof ButtonClicked && ! is_null($service->navigation($mail->uuid())) => $service->navigate($service->navigation($mail->uuid())),
            default => false,
        };
    }

    /**
     * Every frame: the map follows its canvas, asks for what a view lacks, and shows what landed.
     */
    public function tick(): void
    {
        $this->service->tick();
    }
}
