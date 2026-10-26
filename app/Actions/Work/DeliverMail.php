<?php

namespace App\Actions\Work;

use App\Runner\Controllers\StargazerController;
use Closure;
use App\GlobalVariables;
use App\NutsAndBolts\PictureMenu;
use App\RouteMap;
use ReflectionException;
use Surface\Contracts\Windows\Mail\MenuActivated;
use Surface\Contracts\Windows\Mail\WindowResized;
use Surface\Contracts\Windows\Mail\View\Toggled;
use Voyager\Contracts\Signals\NamedSignal;
use Voyager\NutsAndBolts\Collection;

class DeliverMail
{
    protected GlobalVariables $bag;

    public function __construct()
    {
        $this->bag = GlobalVariables::make();
    }

    /**
     * @throws ReflectionException
     */
    public function run(array $incoming_mail): void
    {
        /** @var Collection $remaining_mail */
        $current_route = $this->bag->controllers()->currentRoute();

        /** @var StargazerController $controller */
        $controller = $this->bag->controllers()->get($current_route);

        foreach($incoming_mail as $key => $mail)
        {
            if($this->quitRequested($mail))
            {
                $this->bag->ending = true;
                return;
            }
            elseif($this->windowResized($mail))
            {
                signal($mail);
            }
            elseif(! is_null($route = $this->tabRoute($mail)))
            {
                $this->navigate($route);
                $current_route = $this->bag->controllers()->currentRoute();
                $controller = $this->bag->controllers()->get($current_route);
            }
            elseif (PictureMenu::answered($mail))
            {
                // A save panel's or its alert's answer: the menu that asked takes it.
            }
            elseif ($mail instanceof MenuActivated && $mail->item === 'refresh')
            {
                if (RouteMap::canRefresh($current_route, $this->bag->status_bar->isBusy($current_route))) {
                    $controller->refresh();
                }
            }
            else
            {
                $delivered = $controller->deliver($mail);
                if(!$delivered)
                {
                    signal($mail);
                }
            }
        }
    }

    /**
     * The route whose sidebar tab this mail toggled, or null.
     */
    private function tabRoute(NamedSignal $mail): ?string
    {
        if ($mail instanceof Toggled) {
            foreach ($this->bag->tabs as $route => $tab) {
                if ($tab->uuid() === $mail->uuid()) {
                    return $route;
                }
            }
        }

        return null;
    }

    /**
     * A tab click opens its route. A toggle button unpresses on a second click, so the current
     * route's tab is pressed again.
     * @throws ReflectionException
     */
    private function navigate(string $route): void
    {
        $current = $this->bag->controllers()->currentRoute();

        if ($route === $current) {
            $this->bag->tabs[$route]->setPressed(true);

            return;
        }

        DispatchController::deploy($route, $this->bag);
    }

    private function windowResized(NamedSignal $signal): bool
    {
        $results = false;
        if($signal instanceof WindowResized)
        {
            $results = true;
        }

        return $results;
    }

    private function quitRequested(NamedSignal $item): bool
    {
        if(($item->name() == 'quit.requested') || ($item->name() == 'window.closed.stargazer'))
        {
            return true;
        }

        return false;
    }

    private function warn(string $message): void
    {
        $warn = $this->bag->warn;
        $warn($message);
    }

    private function info(string $message): void
    {
        $info = $this->bag->info;
        $info($message);
    }
}
