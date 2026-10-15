<?php

namespace App\Providers;

use ReflectionException;
use App\NutsAndBolts\Parallel;
use App\NutsAndBolts\RateLimit;
use Voyager\NutsAndBolts\ServiceProvider;
use Voyager\Workflows\AsyncRuntimeManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @throws ReflectionException
     */
    public function register(): void
    {
        ini_set('memory_limit', (string) config('app.memory_limit', '1024M'));
        $this->loadMainWindowRoutes();
        $this->registerParallelWorkflowRunner();

    }

    public function boot(): void
    {
        require_once __DIR__ . "/../../helpers/date.php";
        $this->recordNasaRateLimits();
    }

    /**
     * Every request through the app's Http factory passes the recorder, which keeps the
     * quota api.nasa.gov reports. The cache is asked per response, so a store switch follows.
     * @throws ReflectionException
     */
    private function recordNasaRateLimits(): void
    {
        $limits = new RateLimit(fn () => $this->app->make('cache')->store());
        $this->app->registerInstance(RateLimit::class, $limits);
        $this->app->make('http')->globalMiddleware($limits->middleware());
    }

    /**
     * @throws ReflectionException
     */
    private function registerParallelWorkflowRunner(): void
    {
        $this->app->registerSingleton(Parallel::class, fn($app) => Parallel::make());
    }

    private function loadMainWindowRoutes(): void
    {
        require_once __DIR__ . "/../../routes/window.php";
    }
}
