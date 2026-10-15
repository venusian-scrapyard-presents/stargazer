<?php

namespace App\Runner\Controllers;

use App\GlobalVariables;
use App\Services\NeoWs\NeoWsService;
use App\Workflows\NeoWs\LoadNEOs\LoadNEOsStart;
use App\Workflows\NeoWs\LookupNEO\LookupNEOStart;
use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\RowSelected;
use Surface\Contracts\Windows\Mail\View\Toggled;
use Surface\Contracts\Windows\Mail\View\ValueChanged;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\ToolkitWindow;
use Voyager\Contracts\Signals\NamedSignal;
use Voyager\Workflows\Flow;

class NeoWsController extends StargazerController
{
    public function __construct(
        ToolkitWindow $window,
        public readonly NeoWsService $service
    )
    {
        parent::__construct($window);
    }

    public function title(): string
    {
        return 'Near-Earth Objects';
    }

    public function key(): string
    {
        return 'neows';
    }

    public function menu(): string
    {
        return 'main';
    }

    public function refresh(): void
    {
        $start_node = new LoadNEOsStart($this->service);
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
            $mail instanceof ValueChanged && $service->isDays($mail->uuid()) => $service->daysChanged($mail->value),
            $mail instanceof Toggled && $service->isHazardous($mail->uuid()) => $service->paint(),
            $mail instanceof RowSelected && $service->isObjects($mail->uuid()) => $this->lookup($mail->row),
            default => false,
        };
    }

    protected function reload(): bool
    {
        $this->refresh();

        return true;
    }

    /**
     * A table row picked: look its object up. A cleared selection looks nothing up.
     */
    protected function lookup(?int $row): bool
    {
        $object = $this->service->object($row);

        if (! is_null($object)) {
            $start_node = new LookupNEOStart($this->service, $object);
            new Flow($start_node)->run(GlobalVariables::make());
        }

        return true;
    }
}
