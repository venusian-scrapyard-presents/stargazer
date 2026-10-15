<?php

namespace App\Workflows\DONKI\LoadDONKIEvents;

use App\GlobalVariables;
use App\Gigs\StoreDONKIType;
use App\NutsAndBolts\DonkiEvent;
use App\NutsAndBolts\DonkiRecords;
use Throwable;
use Voyager\NutsAndBolts\Collection;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

/**
 * After a type's span is fetched: hands the records to a StoreDONKIType gig without waiting,
 * and joins them to what was already held outside the span as 'events-<type>'.
 */
class ArchiveDONKIEvents extends Node
{
    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        /** @var GlobalVariables $shared */
        $type = $this->params['type'];
        /** @var Collection $fetched */
        $fetched = $shared->{"fetched-{$type}"};
        [$from, $to] = $shared->{"span-{$type}"};

        $rows = $fetched->map(fn (object $record): array => [
            'record_key' => DonkiRecords::key($type, $record),
            'event_time' => DonkiEvent::from($type, $record)->time,
            'record' => DonkiRecords::encode($record),
        ])->values()->all();

        app('process-workers')->submit(new StoreDONKIType($type, $rows, $from, $to))
            ->error(fn (Throwable $e) => ($shared->warn)("{$type} events not stored: {$e->getMessage()}"));

        $shared->{"events-{$type}"} = new Collection([...$shared->{"stored-{$type}"}->all(), ...$fetched->values()->all()]);

        return null;
    }
}
