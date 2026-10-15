<?php

use App\NutsAndBolts\RateLimit;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Voyager\Cache\ArrayStore;
use Voyager\Cache\Repository;

function rateLimits(): array
{
    $cache = new Repository(new ArrayStore);

    return [new RateLimit(fn () => $cache), $cache];
}

it('keeps the quota api.nasa.gov reports, per key', function () {
    [$limits] = rateLimits();

    $limits->record(
        new Request('GET', 'https://api.nasa.gov/planetary/apod?date=2026-10-09&api_key=MINE'),
        new Response(200, ['X-RateLimit-Limit' => '1000', 'X-RateLimit-Remaining' => '994']),
    );

    expect($limits->reading('MINE'))->toMatchArray(['limit' => 1000, 'remaining' => 994])
        ->and($limits->reading('MINE')['at'])->toBeGreaterThanOrEqual(time() - 1)
        ->and($limits->reading('DEMO_KEY'))->toBeNull();
});

it('reads the headers in any case, and from a rate-limited answer too', function () {
    [$limits] = rateLimits();

    $limits->record(
        new Request('GET', 'https://api.nasa.gov/neo/rest/v1/feed?api_key=DEMO_KEY'),
        new Response(429, ['x-ratelimit-limit' => '10', 'x-ratelimit-remaining' => '0']),
    );

    expect($limits->reading('DEMO_KEY'))->toMatchArray(['limit' => 10, 'remaining' => 0]);
});

it('ignores answers that carry no quota or no key', function () {
    [$limits, $cache] = rateLimits();

    $limits->record(new Request('GET', 'https://images-api.nasa.gov/search?q=jwst'), new Response(200, ['X-RateLimit-Limit' => '1000', 'X-RateLimit-Remaining' => '5']));
    $limits->record(new Request('GET', 'https://api.nasa.gov/planetary/apod?api_key=MINE'), new Response(200));
    $limits->record(new Request('GET', 'https://api.nasa.gov/planetary/apod?api_key=MINE'), new Response(200, ['X-RateLimit-Limit' => 'lots', 'X-RateLimit-Remaining' => '5']));

    expect($limits->reading('MINE'))->toBeNull();
});

it('records through the middleware and hands the response back untouched', function () {
    [$limits] = rateLimits();
    $response = new Response(200, ['X-RateLimit-Limit' => '1000', 'X-RateLimit-Remaining' => '12'], 'body');
    $handler = fn () => GuzzleHttp\Promise\Create::promiseFor($response);

    $answered = $limits->middleware()($handler)(new Request('GET', 'https://api.nasa.gov/EPIC/api/natural?api_key=MINE'), [])->wait();

    expect($answered)->toBe($response)
        ->and($limits->reading('MINE')['remaining'])->toBe(12);
});

it('records every request the Http factory sends, sync or on the loop', function () {
    [$limits] = rateLimits();
    [$http, $runtime] = stargazerHttp();
    $http->globalMiddleware($limits->middleware());
    $http->fake(['*' => $http->response('{}', 200, ['X-RateLimit-Limit' => '1000', 'X-RateLimit-Remaining' => '700'])]);

    $http->get('https://api.nasa.gov/planetary/apod?api_key=SYNC');
    $promise = $http->async()->get('https://api.nasa.gov/planetary/apod?api_key=LOOP');
    $settled = false;
    $promise->then(function () use (&$settled) {
        $settled = true;
    });
    turnUntil($runtime, function () use (&$settled): bool {
        return $settled;
    });

    expect($limits->reading('SYNC')['remaining'])->toBe(700)
        ->and($limits->reading('LOOP')['remaining'])->toBe(700);
});

it('describes a reading for Settings', function () {
    $at = mktime(14, 2, 5, 10, 9, 2026);

    expect(RateLimit::describe(['limit' => 1000, 'remaining' => 994, 'at' => $at]))->toBe('994 of 1,000 requests left this hour, as of 14:02:05.')
        ->and(RateLimit::describe(null))->toBe('No api.nasa.gov request with this key in the last hour.');
});
