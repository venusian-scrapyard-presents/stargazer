<?php

use App\Services\NasaService;
use ProjectSaturnStudios\Stargazer\NasaClient;
use Voyager\Config\Repository as Config;
use Voyager\Http\Async\HttpAsyncManager;
use Voyager\Http\Client\Factory;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\LoopWaiter;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;
use Voyager\IOPools\ResourceRegistry;
use Voyager\IOPools\Waiter\StreamSelectWaiterBackend;
use Voyager\Vessel\ControlPanel;
use Voyager\Workflows\Runtimes\LoopRuntime;

/*
|--------------------------------------------------------------------------
| Stargazer helpers
|--------------------------------------------------------------------------
|
| Workflow nodes get their NasaClient and runtime handed in, so these tests
| build both on one fresh loop with a stray-proof Http fake. No live NASA
| calls, and no booted application.
|
*/

/**
 * A stray-proof Http factory riding a fresh loop, and a Workflows runtime on that same loop.
 * @return array{0: Factory, 1: LoopRuntime}
 */
function stargazerHttp(): array
{
    $registry = new ResourceRegistry();
    $loop = new EventLoop(
        $registry,
        new LoopWaiter($registry, new StreamSelectWaiterBackend(), 5_000_000),
        new GuzzlePromiseEngine(),
    );

    $vessel = new ControlPanel;
    $vessel->registerInstance('config', new Config(['http' => ['async' => [
        'default' => 'curl',
        'drivers' => ['curl' => ['driver' => 'curl']],
    ]]]));
    $vessel->registerInstance('event-loop', $loop);

    $http = new Factory(null, new HttpAsyncManager($vessel));
    $http->preventStrayRequests();

    return [$http, new LoopRuntime($loop)];
}

function fakeNasa(Factory $http): NasaClient
{
    return new NasaClient(api_key: 'TEST_KEY', http: $http);
}

function stargazerFixture(string $name): array
{
    return json_decode((string) file_get_contents(__DIR__."/Fixtures/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Turn the runtime's loop until $done holds, with a frame timer standing in for the sketch's.
 * In the app the frame keeps the loop busy while a graph's fiber waits; an Http fake settles with
 * no I/O, so without the frame the loop has no work and cancels the waiting fiber, as designed.
 */
function turnUntil(LoopRuntime $runtime, Closure $done): void
{
    $loop = $runtime->loop();
    $loop->every(0.001, fn () => null, 'stargazer.test.frame');

    try {
        $loop->until($done);
    } finally {
        $loop->forget('stargazer.test.frame');
    }
}

/**
 * A run owner standing in for a page's service: run tracking only, no media cache (whose
 * runtime needs a booted application).
 */
function runOwner(): NasaService
{
    return new class extends NasaService
    {
        public function __construct() {}

        public function key(): string
        {
            return 'test';
        }
    };
}
