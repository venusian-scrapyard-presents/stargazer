<?php

namespace App\NutsAndBolts;

use App\GlobalVariables;
use App\Models\PersistentVariable;
use App\RouteMap;
use ReflectionException;
use Throwable;

/**
 * Which route the app opens on: one the user picked in Settings, or the last one opened
 * (LAST_RECENT, the default), which RememberLastPage records on every route change.
 */
final class StartPage
{
    public const string VARIABLE = 'app.start_page';

    public const string LAST_PAGE = 'app.last_page';

    public const string LAST_RECENT = 'last_recent';

    /**
     * The stored choice: a route key or LAST_RECENT.
     * @throws ReflectionException
     */
    public static function choice(): string
    {
        $choice = PersistentVariable::recall(self::VARIABLE);

        return is_string($choice) && $choice !== '' ? $choice : self::LAST_RECENT;
    }

    /**
     * The route to open: the choice, or the last page opened; the first route when that is
     * not a route (never opened yet, or a route since removed).
     * @throws ReflectionException
     */
    public static function resolve(): string
    {
        $choice = self::choice();
        $route = $choice === self::LAST_RECENT ? PersistentVariable::recall(self::LAST_PAGE) : $choice;

        return is_string($route) && RouteMap::routes()->has($route) ? $route : RouteMap::firstRoute();
    }

    /**
     * @throws ReflectionException
     */
    public static function choose(string $choice): void
    {
        PersistentVariable::query()->updateOrCreate(['key' => self::VARIABLE], ['value' => $choice]);
    }

    /**
     * Record the page just opened, written in a pool worker.
     * @throws ReflectionException
     */
    public static function remember(string $route): void
    {
        $bag = GlobalVariables::make();

        PersistentVariable::query()->via()
            ->updateOrCreate(['key' => self::LAST_PAGE], ['value' => $route])
            ->error(fn (Throwable $e) => ($bag->warn)("Last page not saved: {$e->getMessage()}"));
    }
}
