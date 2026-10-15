<?php

namespace App\Gigs;

use App\Models\DonkiDay;
use App\Models\DonkiRecord;
use App\NutsAndBolts\DonkiRecords;
use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;

/**
 * Reads, in a pool worker, what donki_records holds of one type over a range and which of its
 * days still need fetching.
 */
class RecallDONKIType implements ShouldPool
{
    /**
     * @param string $type A DonkiRecords::CLASSES key.
     * @param string $from First day, Y-m-d.
     * @param string $to Last day, Y-m-d.
     */
    public function __construct(
        protected string $type,
        protected string $from,
        protected string $to,
    ) {}

    /**
     * Run the gig inside a pool worker.
     * @return array{missing: array{0: string, 1: string}|null, records: list<array{time: int, record: string}>}
     */
    public function handle(): mixed
    {
        $fetched = DonkiDay::query()
            ->where('type', $this->type)
            ->whereBetween('day', [$this->from, $this->to])
            ->pluck('fetched_at', 'day')
            ->map(fn ($at) => (int) $at)
            ->all();

        $start = strtotime($this->from.'T00:00:00Z');
        $end = strtotime($this->to.'T00:00:00Z') + 86400;
        $records = DonkiRecord::query()
            ->where('type', $this->type)
            ->where('event_time', '>=', $start)
            ->where('event_time', '<', $end)
            ->orderBy('event_time')
            ->get(['event_time', 'record'])
            ->map(fn (DonkiRecord $row): array => ['time' => (int) $row->event_time, 'record' => (string) $row->record])
            ->all();

        return [
            'missing' => DonkiRecords::missingSpan($fetched, $this->from, $this->to, time()),
            'records' => $records,
        ];
    }
}
