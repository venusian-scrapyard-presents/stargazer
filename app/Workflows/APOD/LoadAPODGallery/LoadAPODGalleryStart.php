<?php

namespace App\Workflows\APOD\LoadAPODGallery;

use App\GlobalVariables;
use App\Workflows\Fetch;
use App\Workflows\APOD\ArchiveAPODDays;
use App\Services\APOD\APODService;
use Voyager\Workflows\Node;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\SharedBag;

class LoadAPODGalleryStart extends Node
{
    public function __construct(
        protected APODService $apod
    ){
        parent::__construct();
    }

    public function prep(SharedBag $shared): mixed
    {
        return [
            $shared,
            $this->apod->galleryCount(),
        ];
    }

    public function exec(mixed $prepRes): mixed
    {
        /**
         * @var GlobalVariables $bag
         * @var int $count
         */
        [$bag, $count] = $prepRes;
        $nasa = $bag->nasa;
        $apod = $this->apod;

        // The API has no random pick: count() is a run of consecutive days from a random point.
        $fetch = new Fetch(fn () => $nasa->apod()->count($count), 'pictures');
        $apod->failures($fetch);

        // Lay out every cell with its caption first; pictures arrive as their downloads finish.
        $cells = $apod->render(function (GlobalVariables $bag) use ($apod): ?string {
            $bag->sources = $apod->layoutGallery($bag->pictures);

            return null;
        });
        // The gallery's days are stored for the day view; dates already stored are left alone.
        $fetch->next(new ArchiveAPODDays('pictures'))->next($cells);

        $thumbs = new APODThumbnails($apod->media, fn (int $index, ?string $path) => $apod->thumbnailLanded($index, $path));
        $cells->next($thumbs)->next($apod->render(function (GlobalVariables $bag) use ($apod): ?string {
            $apod->galleryLoaded($bag);

            return null;
        }));

        $bag->window->setMenuBar('apod-gallery');

        $bag->flow->start(
            new AsyncFlow($fetch),
            $apod,
            "Picking {$count} days from a random point in the archive…"
        );

        return null;
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return null;
    }
}
