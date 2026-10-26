<?php

namespace App\Actions\Work;

use App\GlobalVariables;
use App\Middleware\RememberLastPage;
use App\Runner\Controllers\StargazerController;

class DispatchController
{
    /** Run around every route change, in order. */
    protected const array MIDDLEWARE = [
        RememberLastPage::class,
    ];

    /**
     * Open a route, through the route-change middleware.
     */
    public static function deploy(string $key, GlobalVariables $bag): void
    {
        app('pipeline')
            ->send($key)
            ->through(self::MIDDLEWARE)
            ->then(fn (string $key) => static::open($key, $bag));
    }

    protected static function open(string $key, GlobalVariables $bag): void
    {
        $routes = $bag->controllers();
        $current_route = $routes->currentRoute();

        if ($key !== $current_route)
        {
            if (!is_null($current_route))
            {
                $routes[$current_route]->hide();
                ($bag->tabs[$current_route] ?? null)?->setPressed(false);
            }

            $routes->current($key);
            ($bag->tabs[$key] ?? null)?->setPressed(true);
            /** @var StargazerController $screen */
            $screen = $routes[$key];
            $bag->window->setMenuBar($screen->menu());
            // A new menu bar starts with every item enabled: the focus paint sets Refresh after it.
            $bag->status_bar->focus($key);
            $screen->mount($bag->deck);
            $screen->show();
        }
    }
}
