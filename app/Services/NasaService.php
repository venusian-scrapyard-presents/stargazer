<?php

namespace App\Services;

use App\Concerns\TracksRuns;
use App\Enums\Outcome;
use App\GlobalVariables;
use App\NutsAndBolts\MediaCache;
use App\NutsAndBolts\StatusBar;
use App\Workflows\Render;
use App\Workflows\Report;
use Closure;
use ReflectionException;
use Surface\Contracts\Windows\Styling\FontSpec;
use Voyager\Workflows\AsyncRuntimeManager;
use Voyager\Workflows\BaseNode;

abstract class NasaService
{
    use TracksRuns;

    protected bool $busy = false;
    protected ?array $last = null;
    protected bool $opened = false;
    protected ?StatusBar $status = null;
    public readonly MediaCache $media;

    /**
     * @throws ReflectionException
     */
    public function __construct()
    {
        $this->media = new MediaCache(
            storage_path('app/stargazer'),
            GlobalVariables::runtime()
        );
        $this->media->clear();
    }

    public function started(int $run, string $message): void
    {
        $bag = GlobalVariables::make();
        $this->busy = true;
        $this->last = null;
        $this->status = $bag->status_bar;
        $this->setBusy(true);
        $this->status->busy($this->key(), $message);
    }

    public function settled(int $run, Outcome $outcome, string $message): void
    {
        $this->busy = false;
        $this->last = [$outcome, $message];
        $this->setBusy(false);
        $this->status->settle($this->key(), $outcome, $message);
    }

    /**
     * Send a node's failure routes to the status reports, then to $paint when given.
     * @param BaseNode $node
     * @param Closure|null $paint (GlobalVariables $bag): void — fills the screen for the failure.
     * @return void
     */
    public function failures(BaseNode $node, ?Closure $paint = null): void
    {
        $failed = $node->on('failed')->next(new Report(Outcome::FAILED));
        $limited = $node->on('rate_limited')->next(new Report(Outcome::RATE_LIMITED));

        // A screen that shows its failures paints them once the outcome is recorded.
        if (! is_null($paint)) {
            $failed->next($this->render($paint));
            $limited->next($this->render($paint));
        }
    }

    /**
     * @param Closure $body (SharedBag, array $params): ?string
     * @return Render
     */
    public function render(Closure $body): Render
    {
        return new Render($this, $body);
    }

    public function done(GlobalVariables $bag, string $message): void
    {
        $bag->outcome = [Outcome::DONE, $message];
    }

    public function empty(GlobalVariables $bag, string $message): void
    {
        $bag->outcome = [Outcome::EMPTY, $message];
    }

    protected function setBusy(bool $busy): void {}

    /**
     * A fixed-width face for orbital elements and JSON: Menlo on macOS, the fontconfig alias elsewhere.
     */
    protected static function monospace(float $size = 12): FontSpec
    {
        return new FontSpec($size, family: PHP_OS_FAMILY === 'Darwin' ? 'Menlo' : 'Monospace');
    }

    /**
     * API text that carries markup (search highlights, entities) as plain text.
     * @param string|null $html
     * @return string
     */
    protected static function plain(?string $html): string
    {
        return trim(html_entity_decode(strip_tags($html ?? ''), ENT_QUOTES | ENT_HTML5));
    }
}
