<?php

namespace App\Workflows\Images\OpenImageItem;

use App\GlobalVariables;
use App\Gigs\StoreImageItem;
use App\Models\ImageItem;
use ProjectSaturnStudios\Stargazer\ImageLibrary\DataObjects\ImageItemData;
use Throwable;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

/**
 * Hands the opened item and the manifest files on the bag ('hrefs') to a StoreImageItem gig and
 * moves on without waiting: storing them is for the next open, not this one.
 */
class ArchiveImageItem extends Node
{
    public function __construct(
        protected ImageItemData $item
    ) {
        parent::__construct();
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        /** @var GlobalVariables $shared */
        app('process-workers')->submit(new StoreImageItem(ImageItem::row($this->item, $shared->hrefs)))
            ->error(fn (Throwable $e) => ($shared->warn)('Image item not stored: '.$e->getMessage()));

        return null;
    }
}
