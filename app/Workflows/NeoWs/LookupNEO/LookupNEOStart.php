<?php

namespace App\Workflows\NeoWs\LookupNEO;

use App\GlobalVariables;
use App\Services\NeoWs\NeoWsService;
use App\Workflows\Fetch;
use ProjectSaturnStudios\Stargazer\NeoWs\DataObjects\NearEarthObject;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

/**
 * A picked object, looked up for its orbit.
 */
class LookupNEOStart extends Node
{
    public function __construct(
        protected NeoWsService $neows,
        protected NearEarthObject $object,
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
        $neows = $this->neows;
        $id = $this->object->id;

        $fetch = new Fetch(fn () => $nasa->neows()->lookup($id), 'neows_object');
        $neows->failures($fetch);
        $fetch->next($neows->render(function (GlobalVariables $bag) use ($neows): ?string {
            $neows->showOrbit($bag);

            return null;
        }));

        $bag->flow->start(
            new AsyncFlow($fetch),
            $neows,
            'Looking up '.($this->object->name ?? $id).'…'
        );

        return null;
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return null;
    }
}
