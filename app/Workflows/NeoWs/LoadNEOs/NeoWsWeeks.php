<?php

namespace App\Workflows\NeoWs\LoadNEOs;

use App\Concerns\FollowsRun;
use App\NutsAndBolts\FetchFailure;
use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use ProjectSaturnStudios\Stargazer\NasaClient;
use ProjectSaturnStudios\Stargazer\NeoWs\DataObjects\NeoFeed;
use Throwable;
use Voyager\Workflows\AsyncBatchNode;
use Voyager\Workflows\SharedBag;

/**
 * NeoWs' feed answers at most seven days, so a longer range is asked for a week at a time, in
 * order, each week retried on its own (a 4xx is final at once). Every week's objects reach the
 * table as that week lands. A week that still fails is counted and the rest carry on — except
 * after a 429: the quota is spent, so the remaining weeks are not asked for. A stale run asks
 * for nothing more. Reads 'neows_start' and 'neows_days'; writes 'neows_failed_weeks',
 * 'neows_week_count' and 'neows_rate_limited'.
 */
class NeoWsWeeks extends AsyncBatchNode
{
    use FollowsRun;

    /** The feed's longest span, in days. */
    public const int SPAN = 7;

    /** The 429 that ended this run's asking, if one did. */
    protected ?FetchFailure $limited = null;

    /**
     * @param NasaClient $nasa
     * @param Closure $landed (NeoFeed $week): void — called per week while the run is current.
     * @param int $wait Seconds between a week's tries, waited on the loop.
     */
    public function __construct(
        protected NasaClient $nasa,
        protected Closure $landed,
        int $wait = 2,
    ) {
        parent::__construct(maxRetries: 3, wait: $wait);
    }

    /**
     * Contiguous spans of at most SPAN days covering $days days from $start.
     * @return list<array{0: string, 1: string}> [from, to], inclusive.
     */
    public static function weeks(DateTimeImmutable $start, int $days): array
    {
        if ($days < 1) {
            throw new InvalidArgumentException("A NeoWs range is at least one day, got {$days}.");
        }

        $weeks = [];
        for ($offset = 0; $offset < $days; $offset += self::SPAN) {
            $length = min(self::SPAN, $days - $offset);
            $from = $start->modify("+{$offset} days");
            $weeks[] = [$from->format('Y-m-d'), $from->modify('+'.($length - 1).' days')->format('Y-m-d')];
        }

        return $weeks;
    }

    public function prepAsync(SharedBag $shared): mixed
    {
        $this->limited = null;

        return self::weeks($shared->neows_start, $shared->neows_days);
    }

    /**
     * @param array{0: string, 1: string} $prepRes
     */
    public function execAsync(mixed $prepRes): mixed
    {
        if ($this->stale()) {
            return null;
        }

        if (! is_null($this->limited)) {
            return $this->limited;
        }

        [$from, $to] = $prepRes;
        $request = $this->nasa->neows()->feed($from, $to);

        return FetchFailure::finalOn4xx($request->async(), $request->url())
            ->then(function (NeoFeed|FetchFailure $week): NeoFeed|FetchFailure {
                if ($week instanceof FetchFailure) {
                    if ($week->isRateLimited()) {
                        $this->limited = $week;
                    }
                } elseif ($this->current()) {
                    ($this->landed)($week);
                }

                return $week;
            });
    }

    public function execFallbackAsync(mixed $prepRes, Throwable $e): mixed
    {
        return FetchFailure::from($e);
    }

    public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
    {
        if ($this->stale()) {
            return 'stale';
        }

        $failures = array_values(array_filter($execRes ?? [], fn (mixed $week): bool => $week instanceof FetchFailure));
        $limited = array_values(array_filter($failures, fn (FetchFailure $failure): bool => $failure->isRateLimited()));
        $shared->neows_failed_weeks = count($failures);
        $shared->neows_week_count = count($execRes ?? []);
        $shared->neows_rate_limited = $limited !== [];

        if ($failures !== [] && count($failures) === count($execRes)) {
            $reason = $limited[0] ?? $failures[0];
            $shared->error = $reason->message();

            return $reason->route();
        }

        return null;
    }
}
