<?php

namespace App\Actions\Bootstrap;

use App\GlobalVariables;
use App\RouteMap;
use App\Models\PersistentVariable;
use App\NutsAndBolts\Busy;
use App\NutsAndBolts\StatusBar;
use App\Runner\Controllers\StargazerController;
use ReflectionException;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Windows\Primitives\TKRow;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\FontWeight;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;
use Surface\Core\MagicAliases\Toolkit;

class BootstrapToolkitWindow
{
    /**
     * @throws ReflectionException
     */
    public static function run(): array
    {
        $bag = GlobalVariables::make();

        [$window, $main_window_width, $main_window_height] = static::initWindow($bag);
        [$container, $body] = static::createWindowContainer($window);
        [$sidebar] = static::buildLayout($bag, $container, $body);
        static::setupRouting($bag, $sidebar);

        return [
            $window,
            $main_window_width,
            $main_window_height
        ];
    }

    /**
     * @throws ReflectionException
     */
    protected static function initWindow(GlobalVariables $bag): array
    {
        $main_window_width = static::getOpeningWidth();
        $main_window_height = static::getOpeningHeight();

        $window = Toolkit::window()->open('stargazer', $main_window_width, $main_window_height, 'main');
        $window->setTitle('Stargazer');
        $bag->window = $window;

        return [
            $window,
            $main_window_width,
            $main_window_height
        ];
    }

    protected static function buildLayout(GlobalVariables $bag, TKColumn $container, TKRow $body): array
    {
        $sidebar = static::createSidebar($body);
        $body->separator('divider', horizontal: false);

        static::createContentDeck($bag, $body);
        $container->separator('rule');

        $footer = static::createFooter($bag, $container);

        return [$sidebar];
    }

    /**
     * @throws ReflectionException
     */
    protected static function setupRouting(GlobalVariables $bag, TKColumn $sidebar): void
    {
        $pinned = [];

        $bag->controllers()->each( function(string $controller_class) use ($bag, $sidebar, &$pinned) {
            /** @var StargazerController $controller */
            $controller = app($controller_class, [$bag->window]);

            $bag->controllers->put($controller->key(), $controller);

            if (RouteMap::isPinned($controller->key())) {
                $pinned[] = $controller;

                return;
            }

            $bag->tabs[$controller->key()] = $sidebar->toggleButton(
                "tab-{$controller->key()}",
                $controller->title()
            );
        });

        // Pinned tabs sit at the bottom: an empty column takes the sidebar's spare height above them.
        if ($pinned !== []) {
            $sidebar->column('spacer')->fill(false, true);
        }

        foreach ($pinned as $controller) {
            $bag->tabs[$controller->key()] = $sidebar->toggleButton(
                "tab-{$controller->key()}",
                $controller->title()
            );
        }

        RouteMap::hidden()->each(function (string $controller_class) use ($bag) {
            /** @var StargazerController $controller */
            $controller = app($controller_class, [$bag->window]);

            $bag->controllers->put($controller->key(), $controller);
        });
    }

    protected static function createFooter(GlobalVariables $bag, TKColumn $container): TKRow
    {
        $footer = $container->row('status', 8, 6);
        $message = $footer->label('message', '');
        $message->fill(true, false);

        $bag->status_bar = new StatusBar(
            static::busyIn($footer, 'busy'),
            $message
        );

        return $footer;
    }

    protected static function createContentDeck(GlobalVariables $bag, TKRow $body): void
    {
        $bag->deck = $body->column('screens');
        $bag->deck->fill();
    }

    protected static function createWindowContainer(ToolkitWindow $window): array
    {
        $shell = $window->column('shell');
        $body = $shell->row('body');
        $body->fill();

        return [$shell, $body];
    }

    protected static function createSidebar(TKRow $body): TKColumn
    {
        // Columns stretch their children across by default; only the deck fills the spare width.
        $nav = $body->column('nav', 6, 12);
        $nav->minSize(180, 0);
        $nav->label('title', 'Stargazer')->setFont(new FontSpec(20, FontWeight::BOLD));
        return $nav;
    }

    /**
     * @throws ReflectionException
     */
    protected static function getOpeningWidth(): int
    {
        $saved = PersistentVariable::recall('window.stargazer.size')['width'] ?? null;

        if(is_int($saved) && $saved > 0)
        {
            $results = $saved;
        }
        else
        {
            $results = config('app.starting_resolution.width', 1024);
        }

        return $results;
    }

    /**
     * @throws ReflectionException
     */
    protected static function getOpeningHeight(): int
    {
        $saved = PersistentVariable::recall('window.stargazer.size')['height'] ?? null;

        if(is_int($saved) && $saved > 0)
        {
            $results = $saved;
        }
        else
        {
            $results = config('app.starting_resolution.height', 768);
        }

        return $results;
    }

    /**
     * A spinner, or an indeterminate progress bar.
     * @param TKPrimitiveGroup $in
     * @param string $name
     * @return Busy
     */
    protected static function busyIn(TKPrimitiveGroup $in, string $name): Busy
    {
        try {
            return new Busy($in->spinner($name));
        } catch (WindowException) {
            return new Busy($in->progressBar($name, null));
        }
    }
}
