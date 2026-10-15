<?php

namespace App;

use App\NutsAndBolts\MediaCache;
use App\NutsAndBolts\Parallel;
use App\NutsAndBolts\StatusBar;
use Closure;
use ProjectSaturnStudios\Stargazer\NasaClient;
use ReflectionException;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Voyager\Contracts\Workflows\AsyncRuntime;
use Voyager\Workflows\AsyncRuntimeManager;
use Voyager\Workflows\SharedBag;
use App\ApplicationState\ApplicationState;
use Surface\Contracts\Windows\ToolkitWindow;
use App\ApplicationState\ApplicationIsNotRunning;
use Surface\Contracts\Bridge\BridgedToolkitSession;
use Voyager\Contracts\IOPools\Loop as LoopInterface;

class GlobalVariables extends SharedBag
{
    private static $instance;

    public ?Closure $info = null;

    public ?Closure $warn = null;

    public int $main_window_width = 0;

    public int $main_window_height = 0;

    public ?ToolkitWindow $window = null;

    public bool $ending = false;

    public ?TKColumn $deck = null;

    public ?StatusBar $status_bar = null;

    public bool $main_window_closed = true;

    public ?LoopInterface $event_loop = null;

    public ?BridgedToolkitSession $session = null;

    public ?Parallel $flow = null;

    public ?NasaClient $nasa = null;

    public ?MediaCache $media = null;

    protected ApplicationState $status;

    public RouteMap $controllers;

    public array $tabs = [];

    private function __construct()
    {
        $this->status = new ApplicationIsNotRunning();
        $this->controllers = RouteMap::routes();
    }

    public function controllers(): RouteMap
    {
        return $this->controllers;
    }

    public function status(): string
    {
        $this->status = $this->status->nextStatus($this);

        return $this->status->status;
    }

    public static function make(): static
    {
        static::$instance ??= new static();
        return static::$instance;
    }

    /**
     * @throws ReflectionException
     */
    public static function runtime(): AsyncRuntime
    {
        return app(AsyncRuntimeManager::class)->driver();
    }
}
