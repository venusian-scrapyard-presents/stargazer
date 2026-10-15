<?php

namespace App\NutsAndBolts;

use App\Enums\Outcome;
use App\GlobalVariables;
use App\Services\NasaService;
use ReflectionException;
use Throwable;
use Voyager\Contracts\Workflows\AsyncRuntime;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\AsyncRuntimeManager;

class Parallel
{
    protected int $in_flight = 0;

    private static ?Parallel $instance = null;

    private function __construct(
        protected AsyncRuntime $runtime
    ) {}

    public function start(AsyncFlow $flow, NasaService $service, string $message): int
    {
        $shared = GlobalVariables::make();
        // The bag outlives runs: a run starts without the last one's outcome or error.
        $shared->outcome = null;
        $shared->error = null;
        $shared->failure = null;
        $run = $service->nextRun();
        $flow->setParams(['run' => $run, 'owner' => $service]);
        $flow->usesRuntime($this->runtime);
        $service->started($run, $message);
        $this->in_flight++;

        $this->runtime->async(fn () => $flow->runAsync($shared))
            ->then(function () use ($service, $run, $shared): void {
                $this->in_flight--;
                if ($service->isCurrent($run)) {
                    [$outcome, $text] = $shared->outcome ?? [Outcome::DONE, ''];
                    $service->settled($run, $outcome, $text);
                }
            })
            ->error(function (Throwable $e) use ($service, $run): void {
                $this->in_flight--;
                if ($service->isCurrent($run)) {
                    $service->settled($run, Outcome::FAILED, $e->getMessage());
                }
            });

        return $run;
    }

    /**
     * @throws ReflectionException
     */
    public static function make(): static
    {
        if(static::$instance) {
            $results = static::get();
        }
        else {
            $runtime = app(AsyncRuntimeManager::class)->driver();
            $media = new MediaCache(storage_path('app/stargazer'), $runtime);

            $results = new static($runtime);
            static::$instance = $results;
        }

        return $results;
    }
    /**
     * @throws ReflectionException
     */
    public static function get(): static
    {
        return app(Parallel::class);
    }
}
