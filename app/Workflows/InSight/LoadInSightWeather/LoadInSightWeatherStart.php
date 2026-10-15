<?php

namespace App\Workflows\InSight\LoadInSightWeather;

use App\GlobalVariables;
use App\Services\InSight\InSightService;
use App\Workflows\Fetch;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

class LoadInSightWeatherStart extends Node
{
    public function __construct(
        protected InSightService $insight
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
        $insight = $this->insight;

        $fetch = new Fetch(fn () => $nasa->insight()->weather(), 'insight_weather');
        $insight->failures($fetch);
        $fetch->next($insight->render(function (GlobalVariables $bag) use ($insight): ?string {
            $insight->weatherLoaded($bag);

            return null;
        }));

        $bag->flow->start(
            new AsyncFlow($fetch),
            $insight,
            'Asking InSight for the weather…'
        );

        return null;
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return null;
    }
}
