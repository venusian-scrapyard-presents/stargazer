<?php

namespace App\Workflows\GIBS\LoadGIBSView;

use App\GlobalVariables;
use App\Services\GIBS\GIBSService;
use App\Workflows\Fetch;
use ProjectSaturnStudios\Stargazer\GIBS\Enums\GibsProjection;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

/**
 * A projection's WMTS capabilities, asked for once: which layers there are and how they are tiled.
 * Once they land the view's tiles are asked for.
 */
class LoadGIBSCapabilitiesStart extends Node
{
    public function __construct(
        protected GIBSService $gibs,
        protected GibsProjection $projection,
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
        $gibs = $this->gibs;
        $projection = $this->projection;

        $fetch = new Fetch(fn () => $nasa->gibs()->wmts($projection)->capabilities(), 'gibs_capabilities');
        $gibs->failures($fetch);
        $fetch->next($gibs->render(function (GlobalVariables $bag) use ($gibs, $projection): ?string {
            $gibs->capabilitiesLoaded($bag, $projection);

            return null;
        }));

        $bag->flow->start(new AsyncFlow($fetch), $gibs, 'Asking GIBS what it has in '.GIBSService::projectionName($projection).'…');

        return null;
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return null;
    }
}
