<?php

use App\Workflows\NeoWs\LoadNEOs\NeoWsWeeks;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\SharedBag;

function weeksFrom(string $start, int $days): array
{
    return NeoWsWeeks::weeks(new DateTimeImmutable($start), $days);
}

it('asks for one day as a one-day span', function () {
    expect(weeksFrom('2026-10-01', 1))->toBe([['2026-10-01', '2026-10-01']]);
});

it('fits seven days in one request', function () {
    expect(weeksFrom('2026-10-01', 7))->toBe([['2026-10-01', '2026-10-07']]);
});

it('splits a longer range into contiguous spans of at most seven days', function () {
    expect(weeksFrom('2026-10-01', 20))->toBe([
        ['2026-10-01', '2026-10-07'],
        ['2026-10-08', '2026-10-14'],
        ['2026-10-15', '2026-10-20'],
    ]);
});

it('covers four full weeks across a month end', function () {
    $weeks = weeksFrom('2026-09-20', 28);

    expect($weeks)->toHaveCount(4)
        ->and($weeks[0][0])->toBe('2026-09-20')
        ->and($weeks[1][0])->toBe('2026-09-27')
        ->and($weeks[3][1])->toBe('2026-10-17');
});

it('refuses a range below one day', function () {
    expect(fn () => weeksFrom('2026-10-01', 0))->toThrow(InvalidArgumentException::class);
});

/**
 * Run NeoWsWeeks over $days from 2026-10-01 against faked answers: a fixture array is a
 * week's feed, an int is that status.
 * @return array{0: mixed, 1: SharedBag, 2: mixed, 3: int} action, bag, http, weeks landed
 */
function runWeeks(array $responses, int $days): array
{
    [$http, $runtime] = stargazerHttp();
    $sequence = $http->sequence();
    foreach ($responses as $response) {
        is_array($response) ? $sequence->push($response) : $sequence->push('status', $response);
    }
    $http->fake(['*' => $sequence]);

    $owner = runOwner();
    $owner->nextRun();
    $landed = 0;
    $weeks = new NeoWsWeeks(fakeNasa($http), function () use (&$landed): void {
        $landed++;
    }, wait: 0);

    $bag = new SharedBag;
    $bag->neows_start = new DateTimeImmutable('2026-10-01');
    $bag->neows_days = $days;
    $flow = new AsyncFlow($weeks, $runtime);
    $flow->setParams(['run' => 1, 'owner' => $owner]);
    $task = $runtime->async(fn () => $flow->runAsync($bag));
    turnUntil($runtime, fn () => $task->settled());

    return [$task->wait(), $bag, $http, $landed];
}

it('hands each week over as it lands and keeps going past a week that fails', function () {
    [$action, $bag, , $landed] = runWeeks([stargazerFixture('neows-feed'), 503, 503, 503], 14);

    expect($landed)->toBe(1)
        ->and($bag->neows_failed_weeks)->toBe(1)
        ->and($bag->neows_week_count)->toBe(2)
        ->and($action)->toBeNull();
});

it('stops asking once NeoWs rate-limits a week, and says so', function () {
    [$action, $bag, $http] = runWeeks([stargazerFixture('neows-feed'), 429], 21);

    expect($action)->toBeNull()
        ->and($bag->neows_failed_weeks)->toBe(2)
        ->and($bag->neows_rate_limited)->toBeTrue();
    $http->assertSentCount(2);
});

it('routes a range where every week failed and one was rate-limited as rate_limited', function () {
    [$action, $bag] = runWeeks([503, 503, 503, 429], 14);

    expect($action)->toBe('rate_limited')
        ->and($bag->neows_rate_limited)->toBeTrue();
});

it('asks for nothing once its run has gone stale', function () {
    [$http, $runtime] = stargazerHttp();
    $http->fake(['*' => stargazerFixture('neows-feed')]);
    $owner = runOwner();
    $owner->nextRun();
    $owner->nextRun();
    $weeks = new NeoWsWeeks(fakeNasa($http), fn () => null, wait: 0);

    $bag = new SharedBag;
    $bag->neows_start = new DateTimeImmutable('2026-10-01');
    $bag->neows_days = 14;
    $flow = new AsyncFlow($weeks, $runtime);
    $flow->setParams(['run' => 1, 'owner' => $owner]);
    $task = $runtime->async(fn () => $flow->runAsync($bag));
    turnUntil($runtime, fn () => $task->settled());

    expect($task->wait())->toBe('stale');
    $http->assertNothingSent();
});
