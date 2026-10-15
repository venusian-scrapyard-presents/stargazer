<?php

namespace App\Runner\Controllers;

use App\GlobalVariables;
use App\Services\TLE\TLEService;
use App\Workflows\TLE\LoadSatellite\LoadSatelliteStart;
use App\Workflows\TLE\SearchSatellites\SearchSatellitesStart;
use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\RowSelected;
use Surface\Contracts\Windows\Mail\View\TextSubmitted;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\ToolkitWindow;
use Voyager\Contracts\Signals\NamedSignal;
use Voyager\Workflows\Flow;

class TLEController extends StargazerController
{
    public function __construct(
        ToolkitWindow $window,
        public readonly TLEService $service
    )
    {
        parent::__construct($window);
    }

    public function title(): string
    {
        return 'Satellite Orbits';
    }

    public function key(): string
    {
        return 'tle';
    }

    public function menu(): string
    {
        return 'main';
    }

    public function refresh(): void
    {
        $start_node = new SearchSatellitesStart($this->service);
        new Flow($start_node)->run(GlobalVariables::make());
    }

    public function index(TKColumn $root): void
    {
        $this->service->mounted($root);
    }

    public function deliver(NamedSignal $mail): bool
    {
        return match (true) {
            $mail instanceof TextSubmitted && $this->service->isQuery($mail->uuid()),
            $mail instanceof ButtonClicked && $this->service->isSearch($mail->uuid()) => $this->reload(),
            $mail instanceof RowSelected && $this->service->isSatellites($mail->uuid()) => $this->select($mail->row),
            default => false,
        };
    }

    protected function reload(): bool
    {
        $this->refresh();

        return true;
    }

    /**
     * A table row picked: fetch its satellite. A cleared selection fetches nothing.
     */
    protected function select(?int $row): bool
    {
        $record = $this->service->record($row);

        if (! is_null($record)) {
            $start_node = new LoadSatelliteStart($this->service, $record);
            new Flow($start_node)->run(GlobalVariables::make());
        }

        return true;
    }
}
