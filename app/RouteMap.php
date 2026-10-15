<?php

namespace App;

use App\Runner\Controllers\APODController;
use App\Runner\Controllers\KeyController;
use App\Runner\Controllers\DONKIController;
use App\Runner\Controllers\EPICController;
use App\Runner\Controllers\GIBSController;
use App\Runner\Controllers\EONETController;
use App\Runner\Controllers\NeoWsController;
use App\Runner\Controllers\InSightController;
use App\Runner\Controllers\TLEController;
use App\Runner\Controllers\TechTransferController;
use App\Runner\Controllers\ImagesController;
use App\Runner\Controllers\SettingsController;
use Voyager\NutsAndBolts\Collection;

class RouteMap extends Collection
{
    private ?string $current_route = null;

    protected static $routes = [
        'apod' => APODController::class,
        'epic' => EPICController::class,
        'eonet' => EONETController::class,
        'donki' => DONKIController::class,
        'neows' => NeoWsController::class,
        'insight' => InSightController::class,
        'tle' => TLEController::class,
        'images' => ImagesController::class,
        'techtransfer' => TechTransferController::class,
        'gibs' => GIBSController::class,
        'settings' => SettingsController::class,
    ];

    /** Routes whose tab sits at the bottom of the sidebar, in this order. */
    protected static $pinned = [
        'settings',
    ];

    /** Routes without a sidebar tab: the app opens them itself. */
    protected static $hidden = [
        'key' => KeyController::class,
    ];

    public function currentRoute(): ?string
    {
        return $this->current_route;
    }

    public function current(string $key): void
    {
        $this->current_route = $key;
    }

    public static function routes(): static
    {
        return new static(static::$routes);
    }

    public static function hidden(): static
    {
        return new static(static::$hidden);
    }

    public static function isPinned(string $key): bool
    {
        return in_array($key, static::$pinned, true);
    }

    public static function firstRoute(): string
    {
        return array_key_first(static::$routes);
    }
}
