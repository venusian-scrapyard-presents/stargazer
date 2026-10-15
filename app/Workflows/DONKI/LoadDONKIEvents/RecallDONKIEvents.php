<?php

namespace App\Workflows\DONKI\LoadDONKIEvents;

use App\Concerns\FollowsRun;
use App\Gigs\RecallDONKIType;
use App\NutsAndBolts\DonkiRecords;
use Throwable;
use Voyager\NutsAndBolts\Collection;
use Voyager\Workflows\AsyncNode;
use Voyager\Workflows\SharedBag;

/**
 * One type's stored records over the range, read in a pool worker. Every day held: the records
 * land on the bag as 'events-<type>' and route 'stored'. Some days missing: the records outside
 * that span land as 'stored-<type>', the span as 'span-<type>', and it routes 'fetch'. A failed
 * read fetches the whole range.
 */
class RecallDONKIEvents extends AsyncNode
{
    use FollowsRun;

    /**
     * @param string $from First day, Y-m-d.
     * @param string $to Last day, Y-m-d.
     */
    public function __construct(
        protected string $from,
        protected string $to,
    ) {
        parent::__construct();
    }

    public function prepAsync(SharedBag $shared): mixed
    {
        return $this->params['type'];
    }

    public function execAsync(mixed $prepRes): mixed
    {
        return $this->stale() ? null : app('process-workers')->submit(new RecallDONKIType($prepRes, $this->from, $this->to));
    }

    public function execFallbackAsync(mixed $prepRes, Throwable $e): mixed
    {
        return null;
    }

    public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
    {
        if ($this->stale()) {
            return 'stale';
        }

        $type = $prepRes;
        $missing = is_array($execRes) ? $execRes['missing'] : [$this->from, $this->to];
        $records = is_array($execRes) ? $execRes['records'] : [];

        if (is_null($missing)) {
            $shared->{"events-{$type}"} = $this->decode($type, $records);

            return 'stored';
        }

        // What is held outside the span stays; the span is asked for again.
        $start = strtotime($missing[0].'T00:00:00Z');
        $end = strtotime($missing[1].'T00:00:00Z') + 86400;
        $outside = array_values(array_filter($records, fn (array $row): bool => $row['time'] < $start || $row['time'] >= $end));
        $shared->{"stored-{$type}"} = $this->decode($type, $outside);
        $shared->{"span-{$type}"} = $missing;

        return 'fetch';
    }

    /**
     * @param list<array{time: int, record: string}> $rows
     */
    protected function decode(string $type, array $rows): Collection
    {
        return new Collection(array_map(fn (array $row): object => DonkiRecords::decode($type, $row['record']), $rows));
    }
}
