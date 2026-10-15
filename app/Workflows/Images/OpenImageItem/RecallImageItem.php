<?php

namespace App\Workflows\Images\OpenImageItem;

use App\Concerns\FollowsRun;
use App\Models\ImageItem;
use Throwable;
use Voyager\Workflows\AsyncNode;
use Voyager\Workflows\SharedBag;

/**
 * Looks the item up in image_items, read in a pool worker. A stored item's manifest files land
 * on the bag as 'hrefs' and route 'stored'; a missing one (or a failed read) routes 'fetch'.
 */
class RecallImageItem extends AsyncNode
{
    use FollowsRun;

    public function __construct(
        protected string $nasa_id
    ) {
        parent::__construct();
    }

    public function execAsync(mixed $prepRes): mixed
    {
        return $this->stale() ? null : ImageItem::query()->where('nasa_id', $this->nasa_id)->via()->first();
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

        if ($execRes instanceof ImageItem) {
            $shared->hrefs = $execRes->hrefs();

            return 'stored';
        }

        return 'fetch';
    }
}
