<?php

namespace App\Workflows\TLE\LoadSatellite;

use App\GlobalVariables;
use App\Services\TLE\TLEService;
use App\Workflows\Fetch;
use ProjectSaturnStudios\Stargazer\TLE\DataObjects\TleRecord;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

/**
 * A picked satellite, fetched fresh: its elements may be newer than the search listing's.
 */
class LoadSatelliteStart extends Node
{
    public function __construct(
        protected TLEService $tle,
        protected TleRecord $record,
    ){
        parent::__construct();
    }

    public function prep(SharedBag $shared): mixed
    {
        return $shared;
    }

    public function exec(mixed $prepRes): mixed
    {
        /** @var GlobalVariables $bag */
        $bag = $prepRes;
        $nasa = $bag->nasa;
        $tle = $this->tle;
        $id = $this->record->satelliteId;

        $fetch = new Fetch(fn () => $nasa->tle()->satellite($id), 'tle_satellite');
        $tle->failures($fetch);
        $fetch->next($tle->render(function (GlobalVariables $bag) use ($tle): ?string {
            $tle->showElements($bag);

            return null;
        }));

        $bag->flow->start(
            new AsyncFlow($fetch),
            $tle,
            "Fetching {$this->record->name}…"
        );

        return null;
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return null;
    }
}
