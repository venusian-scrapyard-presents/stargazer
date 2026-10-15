<?php

namespace App\Runner\Controllers;

use App\GlobalVariables;
use App\Services\InSight\InSightService;
use App\Workflows\InSight\LoadInSightWeather\LoadInSightWeatherStart;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\ToolkitWindow;
use Voyager\Contracts\Signals\NamedSignal;
use Voyager\Workflows\Flow;

class InSightController extends StargazerController
{
    public function __construct(
        ToolkitWindow $window,
        public readonly InSightService $service
    )
    {
        parent::__construct($window);
    }

    public function title(): string
    {
        return 'InSight';
    }

    public function key(): string
    {
        return 'insight';
    }

    public function menu(): string
    {
        return 'main';
    }

    public function refresh(): void
    {
        $start_node = new LoadInSightWeatherStart($this->service);
        new Flow($start_node)->run(GlobalVariables::make());
    }

    public function index(TKColumn $root): void
    {
        $this->service->mounted($root);
    }

    /**
     * The page has no controls of its own.
     */
    public function deliver(NamedSignal $mail): bool
    {
        return false;
    }
}
