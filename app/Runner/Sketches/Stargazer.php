<?php

namespace App\Runner\Sketches;

use Surface\Contracts\Windows\ToolkitWindow;
use App\Actions\Work\DispatchController;
use App\GlobalVariables;
use App\RouteMap;
use App\NutsAndBolts\CacheStore;
use App\NutsAndBolts\NasaKey;
use App\NutsAndBolts\StartPage;
use App\NutsAndBolts\Parallel;
use App\Runner\Controllers\StargazerController;
use ReflectionException;
use Voyager\IOPools\IOPools;
use App\Actions\Work\DeliverMail;
use Surface\Core\MagicAliases\Toolkit;
use Voyager\Contracts\Sketches\SketchLoopResult;
use App\Actions\Bootstrap\BootstrapToolkitWindow;
use App\Actions\Bootstrap\BootstrapToolkitEngine;
use Voyager\Workflows\AsyncRuntimeManager;

class Stargazer extends Sketch
{
    /**
     * The sketch description.
     *
     * @var string
     */
    protected string $description = 'Run the NASA Stargazer Application';

    private ?GlobalVariables $bag = null;

    /**
     * Prepare the sketch before the first frame.
     * @throws ReflectionException
     */
    public function boot(): void
    {
        $this->provisionGlobalVariables();
        CacheStore::start();
        if (! is_null(CacheStore::$unreachable)) {
            $this->warn('Redis did not answer ('.CacheStore::$unreachable.'); caching to file for this run.');
        }
        $this->setupWorkflowRuntime();
    }

    /**
     * One frame. The runner calls it at the refresh rate.
     *
     * @param list<object> $mail what the loop delivered since the last frame; empty when nothing did
     * @throws ReflectionException
     */
    public function loop(array $mail = []): SketchLoopResult
    {
        match($this->bag->status())
        {
            "BOOTED" => $this->runApplication(),
            "RUNNING" => $this->deliverMail($mail),
            default => null
        };

        // Quit closes the windows while delivering its mail, and a red-button close removes the
        // window's primitives a frame before its mail arrives: nothing ticks on either.
        foreach (self::ticks($this->bag->status(), $this->bag->window) ? $this->bag->controllers() : [] as $screen) {
            if ($screen->isMounted()) {
                $screen->tick();
            }
        }

        return $this->bag->status() == 'RUNNING'
            ? SketchLoopResult::CONTINUE
            : SketchLoopResult::STOP;
    }

    /** Whether the screens tick this frame: the app runs and its main window is still open. */
    public static function ticks(string $status, ?ToolkitWindow $window): bool
    {
        return $status === 'RUNNING' && ($window?->isOpen() ?? false);
    }

    /**
     * Release resources after the loop ends or fails.
     */
    public function shutdown(): void
    {
        try {
            $this->endApplication();
        } finally {
            $this->info("Application closed.");
        }
    }

    /**
     * @throws ReflectionException
     */
    private function setupWorkflowRuntime(): void
    {
        $this->bag->flow = Parallel::get();
        $stored = NasaKey::stored();
        $this->bag->nasa = is_null($stored) ? app('nasa') : NasaKey::apply($stored);
    }

    /**
     * @throws ReflectionException
     */
    private function runApplication(): void
    {
        [
            $this->bag->window,
            $this->bag->main_window_width,
            $this->bag->main_window_height,
        ] = BootstrapToolkitWindow::run();

        if(is_null($this->bag->window)) $this->error("Application did not start.");
        else
        {
            $this->bag->window->present();
            // No stored NASA key: the key form runs first and opens the start page once saved.
            $start = is_null(NasaKey::stored()) ? 'key' : StartPage::resolve();
            DispatchController::deploy($start, $this->bag);
            $this->info("Application started");
            $this->info('Pick a tab; Refresh (⌘R / Ctrl+R) re-runs the current one; Quit ends the sketch.');
        }
    }

    private function endApplication(): void
    {
        $this->warn("Terminating Application");
        Toolkit::window()->closeAll();
        $this->bag->session->leaveLoop();
        $this->bag->session->disconnect();
    }

    /**
     * @throws ReflectionException
     */
    private function provisionGlobalVariables(): void
    {
        $this->bag = GlobalVariables::make();
        $this->bag->event_loop = IOPools::eventLoop();
        $this->bag->session = BootstrapToolkitEngine::run($this->bag->event_loop);

        $this->bag->info = fn(string $output) => $this->info($output);
        $this->bag->warn = fn(string $output) => $this->warn($output);

    }

    /**
     * @throws ReflectionException
     */
    private function deliverMail(array $mail = []): void
    {
        if(!empty($mail))
        {
            new DeliverMail()->run($mail);
        }
    }
}
