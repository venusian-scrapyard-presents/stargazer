<?php

namespace App\Runner\Controllers;

use App\Services\Settings\KeyService;
use ReflectionException;
use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\TextSubmitted;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\ToolkitWindow;
use Voyager\Contracts\Signals\NamedSignal;

/**
 * Runs first while no NASA key is stored. It has no sidebar tab.
 */
class KeyController extends StargazerController
{
    public function __construct(
        ToolkitWindow $window,
        public readonly KeyService $service
    )
    {
        parent::__construct($window);
    }

    public function title(): string
    {
        return 'NASA API Key';
    }

    public function key(): string
    {
        return 'key';
    }

    public function menu(): string
    {
        return 'main';
    }

    public function refresh(): void {}

    public function index(TKColumn $root): void
    {
        $this->service->mounted($root);
    }

    /**
     * @throws ReflectionException
     */
    public function deliver(NamedSignal $mail): bool
    {
        return match (true) {
            $mail instanceof ButtonClicked && $this->service->isSave($mail->uuid()),
            $mail instanceof TextSubmitted && $this->service->isKeyInput($mail->uuid()) => $this->service->save(),
            default => false,
        };
    }
}
