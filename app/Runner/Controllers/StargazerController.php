<?php

namespace App\Runner\Controllers;

use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Windows\ToolkitWindow;
use Voyager\Contracts\Signals\NamedSignal;

abstract class StargazerController
{
    protected bool $busy = false;

    protected bool $opened = false;

    protected ?TKColumn $root = null;

    public function __construct(
        public readonly ToolkitWindow $window
    ) {}

    abstract public function key(): string;

    abstract public function refresh(): void;

    abstract public function title(): string;

    /** The menu profile (config/windows.php) the window shows while this route is current. */
    abstract public function menu(): string;

    abstract public function deliver(NamedSignal $mail): bool;

    abstract public function index(TKColumn $root): void;

    public function show(): void
    {
        $this->root?->show();

        if (! $this->opened) {
            $this->opened = true;
            $this->refresh();
        }
    }

    public function hide(): void
    {
        $this->root?->hide();
    }

    public function isMounted(): bool
    {
        return ! is_null($this->root);
    }

    public function mount(TKPrimitiveGroup $screens): void
    {
        if (is_null($this->root)) {
            $this->root = $screens->column($this->key(), 10, 16);
            $this->root->fill();
            $this->index($this->root);
        }
    }

    public function tick(): void {}
}
