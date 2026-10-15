<?php

namespace App\Workflows\APOD;

use App\GlobalVariables;
use App\Gigs\StoreAPODDays;
use ProjectSaturnStudios\Stargazer\APOD\DataObjects\AstronomyPicture;
use Throwable;
use Voyager\NutsAndBolts\Collection;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

/**
 * Hands the days a fetch put on the bag to a StoreAPODDays gig and moves on without waiting:
 * storing them is for the next load, not this one.
 */
class ArchiveAPODDays extends Node
{
    /**
     * @param string $from The bag key: one AstronomyPicture, or a Collection of them.
     */
    public function __construct(
        protected string $from
    ) {
        parent::__construct();
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        /** @var GlobalVariables $shared */
        $held = $shared->{$this->from};
        $pictures = $held instanceof Collection ? $held->values()->all() : [$held];
        $days = array_map(fn (AstronomyPicture $picture): array => $picture->toArray(), $pictures);

        app('process-workers')->submit(new StoreAPODDays($days))
            ->error(fn (Throwable $e) => ($shared->warn)('APOD days not stored: '.$e->getMessage()));

        return null;
    }
}
