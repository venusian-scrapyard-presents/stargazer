<?php

namespace App\Workflows\TLE\SearchSatellites;

use App\GlobalVariables;
use App\Services\TLE\TLEService;
use App\Workflows\Fetch;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

/**
 * Searches the TLE catalogue by name; an empty query lists the first page of satellites.
 */
class SearchSatellitesStart extends Node
{
    public function __construct(
        protected TLEService $tle
    ){
        parent::__construct();
    }

    public function prep(SharedBag $shared): mixed
    {
        return [$shared, $this->tle->query()];
    }

    public function exec(mixed $prepRes): mixed
    {
        /**
         * @var GlobalVariables $bag
         * @var string $query
         */
        [$bag, $query] = $prepRes;
        $nasa = $bag->nasa;
        $tle = $this->tle;

        $fetch = new Fetch(fn () => $query === '' ? $nasa->tle()->collection() : $nasa->tle()->search($query), 'tle_found');
        $tle->failures($fetch);
        $fetch->next($tle->render(function (GlobalVariables $bag) use ($tle, $query): ?string {
            $tle->showResults($bag, $query);

            return null;
        }));

        $bag->flow->start(
            new AsyncFlow($fetch),
            $tle,
            $query === '' ? 'Listing satellites…' : "Searching for “{$query}”…"
        );

        return null;
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return null;
    }
}
