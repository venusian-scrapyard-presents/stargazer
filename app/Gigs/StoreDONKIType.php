<?php

namespace App\Gigs;

use App\Models\DonkiDay;
use App\Models\DonkiRecord;
use App\NutsAndBolts\DonkiRecords;
use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;

/**
 * Stores, in a pool worker, one type's records from a fetch and marks the days it covered as
 * fetched now. A record already stored is replaced, so a revision upstream lands here too.
 */
class StoreDONKIType implements ShouldPool
{
    /**
     * @param string $type A DonkiRecords::CLASSES key.
     * @param list<array{record_key: string, event_time: int, record: string}> $records
     * @param string $from First day the fetch covered, Y-m-d.
     * @param string $to Last day the fetch covered, Y-m-d.
     */
    public function __construct(
        protected string $type,
        protected array $records,
        protected string $from,
        protected string $to,
    ) {}

    /**
     * Run the gig inside a pool worker.
     * @return int The records written.
     */
    public function handle(): mixed
    {
        $now = time();
        $stamp = date('Y-m-d H:i:s', $now);

        $rows = array_map(fn (array $record): array => [...$record, 'type' => $this->type, 'created_at' => $stamp, 'updated_at' => $stamp], $this->records);
        foreach (array_chunk($rows, 200) as $chunk) {
            DonkiRecord::query()->upsert($chunk, ['type', 'record_key'], ['event_time', 'record', 'updated_at']);
        }

        $days = array_map(fn (string $day): array => [
            'type' => $this->type, 'day' => $day, 'fetched_at' => $now, 'created_at' => $stamp, 'updated_at' => $stamp,
        ], DonkiRecords::days($this->from, $this->to));
        DonkiDay::query()->upsert($days, ['type', 'day'], ['fetched_at', 'updated_at']);

        return count($rows);
    }
}
