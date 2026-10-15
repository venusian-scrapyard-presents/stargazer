<?php

namespace App\Middleware;

use App\NutsAndBolts\StartPage;
use App\RouteMap;
use Closure;
use ReflectionException;

/**
 * Route-change middleware: while the start page is LAST_RECENT, records each sidebar route
 * opened. Hidden routes (the key form) are never a start page.
 */
class RememberLastPage
{
    /**
     * @param string $route
     * @param Closure $next (string $route): mixed
     * @throws ReflectionException
     */
    public function handle(string $route, Closure $next): mixed
    {
        $result = $next($route);

        if (RouteMap::routes()->has($route) && StartPage::choice() === StartPage::LAST_RECENT) {
            StartPage::remember($route);
        }

        return $result;
    }
}
