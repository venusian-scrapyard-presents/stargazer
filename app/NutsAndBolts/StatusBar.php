<?php

namespace App\NutsAndBolts;

use App\Enums\Outcome;
use Closure;
use Surface\NutsAndBolts\Color;
use Surface\Contracts\Windows\Primitives\TKLabel;

/**
 * One status line per screen; the row shows the active screen's. A run that ends while its
 * screen is hidden keeps its line for when the screen comes back.
 */
final class StatusBar
{
    /** @var array<string, array{0: bool, 1: Outcome, 2: string}> */
    protected array $states = [];

    protected string $active = '';

    /**
     * @param Closure|null $changed (string $screen, bool $busy): void — the shown screen and whether
     *                              it has a run in flight, after each paint.
     */
    public function __construct(
        protected Busy $busy,
        protected TKLabel $label,
        protected ?Closure $changed = null,
    ) {}

    public function focus(string $screen): void
    {
        $this->active = $screen;
        $this->paint();
    }

    public function busy(string $screen, string $message): void
    {
        $this->states[$screen] = [true, Outcome::DONE, $message];
        $this->paint();
    }

    public function isBusy(string $screen): bool
    {
        return $this->states[$screen][0] ?? false;
    }

    public function settle(string $screen, Outcome $outcome, string $message): void
    {
        $this->states[$screen] = [false, $outcome, $message];
        $this->paint();
    }

    protected function paint(): void
    {
        [$busy, $outcome, $message] = $this->states[$this->active] ?? [false, Outcome::DONE, ''];

        $this->busy->set($busy);
        $this->label->setText($message);
        $this->label->setTextColor($outcome->isError() ? Color::hex('#d9534f') : null);
        if (! is_null($this->changed)) {
            ($this->changed)($this->active, $busy);
        }
    }
}
