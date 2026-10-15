<?php

namespace App\Gigs;

use App\Models\ImageItem;
use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;

/**
 * Stores an opened Image Library item and its manifest files in a pool worker; an item already
 * stored is left as it is.
 */
class StoreImageItem implements ShouldPool
{
    /**
     * @param array<string, string|null> $row ImageItem::row().
     */
    public function __construct(
        protected array $row
    ) {}

    /**
     * Run the gig inside a pool worker.
     * @return int The rows inserted.
     */
    public function handle(): mixed
    {
        $now = date('Y-m-d H:i:s');

        return ImageItem::query()->insertOrIgnore([[...$this->row, 'created_at' => $now, 'updated_at' => $now]]);
    }
}
