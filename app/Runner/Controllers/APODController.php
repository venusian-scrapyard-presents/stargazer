<?php

namespace App\Runner\Controllers;

use App\GlobalVariables;
use App\Services\APOD\APODService;
use App\Workflows\APOD\LoadAPODDay\LoadAPODDayStart;
use App\Workflows\APOD\LoadAPODGallery\LoadAPODGalleryStart;
use DateInvalidTimeZoneException;
use DateTimeImmutable;
use DateMalformedStringException;
use ReflectionException;
use Surface\Contracts\Windows\Mail\MenuActivated;
use Surface\Contracts\Windows\Mail\MenuToggled;
use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\DateChanged;
use Surface\Contracts\Windows\Mail\View\Toggled;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Voyager\Contracts\Signals\NamedSignal;
use Voyager\NutsAndBolts\Collection;
use Voyager\Workflows\Flow;

class APODController extends StargazerController
{
    protected bool $is_showing_gallery = false;

    public function __construct(
        ToolkitWindow $window,
        public readonly APODService $service
    )
    {
        parent::__construct($window);
    }

    public function title(): string
    {
        return 'Picture of the Day';
    }

    public function key(): string
    {
        return 'apod';
    }

    public function menu(): string
    {
        return $this->is_showing_gallery ? 'apod-gallery' : 'apod';
    }

    /**
     * Show Grid: the gallery; Show Single Date: the day. Each load sets its own menu bar.
     * @param bool $on
     * @return void
     */
    public function showGallery(bool $on = true): void
    {
        $this->is_showing_gallery = $on;
        $this->service->showGalleryPanel($on);
        $this->refresh();
    }

    /**
     * A gallery cell's Open: back to the single view on that day.
     * @param string $date
     * @return void
     * @throws DateMalformedStringException
     */
    public function openDay(string $date): void
    {
        $this->service->date()->setDate(new DateTimeImmutable($date));
        $this->showGallery(false);
    }


    public function deliver(NamedSignal $mail): bool
    {
        // The day's picture zooms on its own clicks and buttons.
        if ($this->service->viewer()->handles($mail)) {
            return true;
        }

        $results = match($mail::class)
        {
            DateChanged::class => $this->service->updateDate($mail),
            Toggled::class => $this->service->updateHd($mail, $this),
            ButtonClicked::class => $this->service->reloadDay($mail, $this),
            MenuActivated::class => $this->service->menuActivated($mail),
            MenuToggled::class => $this->service->menuReaction($mail, $this),
            default => $this->defaultMail($mail),
        };

        return $results;
    }

    public function isShowingGallery(): bool
    {
        return $this->is_showing_gallery;
    }

    /**
     * Every frame: the day's picture follows its canvas and shows what changed.
     */
    public function tick(): void
    {
        $this->service->viewer()->tick();
    }

    public function refresh(): void
    {
        $this->is_showing_gallery
            ? $this->getGallery()
            : $this->getDay();
    }

    /**
     * @throws ReflectionException
     * @throws DateMalformedStringException
     * @throws DateInvalidTimeZoneException
     */
    public function index(TKColumn $root): void
    {
        $this->service->mounted($root);
    }

    protected function defaultMail(NamedSignal $mail): bool
    {
        dump($mail, $mail->name());
        return false;
    }

    protected function getDay(): void
    {
        $start_node = new LoadAPODDayStart($this->service);
        new Flow($start_node)->run(GlobalVariables::make());
    }

    protected function getGallery(): void
    {
        $start_node = new LoadAPODGalleryStart($this->service);
        new Flow($start_node)->run(GlobalVariables::make());
    }
}
