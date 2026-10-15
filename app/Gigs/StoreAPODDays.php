<?php

namespace App\Gigs;

use App\Models\ApodDay;
use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;

/**
 * Stores APOD days in a pool worker; a date already stored is left as it is.
 */
class StoreAPODDays implements ShouldPool
{
    /**
     * @param list<array<string, string|null>> $days AstronomyPicture::toArray() rows.
     */
    public function __construct(
        protected array $days
    ) {}

    /**
     * Run the gig inside a pool worker.
     * @return int The rows inserted.
     */
    public function handle(): mixed
    {
        $now = date('Y-m-d H:i:s');
        $rows = array_map(fn (array $day): array => [...$day, 'created_at' => $now, 'updated_at' => $now], $this->days);

        return ApodDay::query()->insertOrIgnore($rows);
    }
}
