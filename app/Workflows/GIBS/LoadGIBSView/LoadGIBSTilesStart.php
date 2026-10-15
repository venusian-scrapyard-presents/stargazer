<?php

namespace App\Workflows\GIBS\LoadGIBSView;

use App\GlobalVariables;
use App\Services\GIBS\GIBSService;
use ProjectSaturnStudios\Stargazer\GIBS\Enums\GibsProjection;
use ProjectSaturnStudios\Stargazer\GIBS\Vector\GibsVectorTile;
use Voyager\Http\Client\Response;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

/**
 * The imagery and overlay tiles a view lacks, drawn as each lands.
 */
class LoadGIBSTilesStart extends Node
{
    /**
     * @param list<array<string, mixed>> $wanted The tiles to fetch; a vector one carries 'vector'.
     */
    public function __construct(
        protected GIBSService $gibs,
        protected GibsProjection $projection,
        protected array $wanted,
        protected string $message,
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
        $wanted = $this->wanted;

        $bag->gibs_tiles = $wanted;
        $tiles = new GIBSTiles(
            fn (array $tile) => isset($tile['vector'])
                ? $nasa->gibs()->wmts($projection)->vectorTile($tile['layer'], $tile['set'], $tile['zoom'], $tile['row'], $tile['col'], $tile['time'])
                : $nasa->gibs()->wmts($projection)->tile($tile['layer'], $tile['set'], $tile['zoom'], $tile['row'], $tile['col'], $tile['format'], $tile['time']),
            fn (array $tile, Response|GibsVectorTile $result) => $result instanceof GibsVectorTile ? $gibs->vectorArrived($tile, $result) : $gibs->arrived($tile, $result),
        );
        $tiles->next($gibs->render(function (GlobalVariables $bag) use ($gibs, $wanted): ?string {
            $gibs->tilesLoaded($bag, count($wanted));

            return null;
        }));

        $bag->flow->start(new AsyncFlow($tiles), $gibs, $this->message);

        return null;
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return null;
    }
}
