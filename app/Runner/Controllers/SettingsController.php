<?php

namespace App\Runner\Controllers;

use App\Services\Settings\SettingsService;
use ReflectionException;
use Surface\Contracts\Windows\Mail\Services\AlertAnswered;
use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\SelectionChanged;
use Surface\Contracts\Windows\Mail\View\TextSubmitted;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\ToolkitWindow;
use Voyager\Contracts\Signals\NamedSignal;

class SettingsController extends StargazerController
{
    public function __construct(
        ToolkitWindow $window,
        public readonly SettingsService $service
    )
    {
        parent::__construct($window);
    }

    public function title(): string
    {
        return 'Settings';
    }

    public function key(): string
    {
        return 'settings';
    }

    public function menu(): string
    {
        return 'main';
    }

    /**
     * @throws ReflectionException
     */
    public function refresh(): void
    {
        $this->service->load();
    }

    public function index(TKColumn $root): void
    {
        $this->service->mounted($root);
    }

    /**
     * @throws ReflectionException
     */
    public function tick(): void
    {
        if ($this->root?->isVisible()) {
            $this->service->tick();
        }
    }

    /**
     * @throws ReflectionException
     */
    public function deliver(NamedSignal $mail): bool
    {
        return match (true) {
            $mail instanceof ButtonClicked && $this->service->isSaveKey($mail->uuid()),
            $mail instanceof TextSubmitted && $this->service->isKeyInput($mail->uuid()) => $this->service->saveKey(),
            $mail instanceof SelectionChanged && $this->service->isStartPage($mail->uuid()) => $this->service->chooseStartPage($mail->index),
            $mail instanceof SelectionChanged && $this->service->isStore($mail->uuid()) => $this->service->chooseStore($mail->index),
            $mail instanceof ButtonClicked && $this->service->isApplyStore($mail->uuid()) => $this->service->applyStore(),
            $mail instanceof ButtonClicked && $this->service->isReset($mail->uuid()) => $this->service->askReset(),
            $mail instanceof AlertAnswered && $this->service->isResetAlert($mail->id) => $this->service->resetAnswered($mail->button),
            default => false,
        };
    }
}
