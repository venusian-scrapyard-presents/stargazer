<?php

namespace App\Workflows\EPIC\LoadEPICDay;

use App\GlobalVariables;
use App\Services\EPIC\EPICService;
use App\Workflows\Fetch;
use ProjectSaturnStudios\Stargazer\EPIC\Enums\EpicCollection;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

/**
 * Lists a day's EPIC frames, then downloads them all; the slider turns the Earth once they land.
 */
class LoadEPICDayStart extends Node
{
    /**
     * @param EPICService $epic
     * @param string|null $date null for the latest day EPIC has.
     */
    public function __construct(
        protected EPICService $epic,
        protected ?string $date,
    ){
        parent::__construct();
    }

    public function prep(SharedBag $shared): mixed
    {
        return [
            $shared,
            $this->epic->collection(),
        ];
    }

    public function exec(mixed $prepRes): mixed
    {
        /**
         * @var GlobalVariables $bag
         * @var EpicCollection $collection
         */
        [$bag, $collection] = $prepRes;
        $nasa = $bag->nasa;
        $epic = $this->epic;
        $date = $this->date;

        $fetch = new Fetch(fn () => $nasa->epic()->{$collection->value}($date), 'epic_images');
        $epic->failures($fetch);

        $listed = $epic->render(fn (GlobalVariables $bag): ?string => $epic->listed($bag, $date) ? null : 'empty');
        $fetch->next($listed);

        $frames = new EPICFrames($epic->media, $collection, fn (int $settled, int $total) => $epic->progressed($settled, $total));
        $epic->failures($frames);
        $listed->next($frames)->next($epic->render(function (GlobalVariables $bag) use ($epic): ?string {
            $epic->framesLoaded($bag);

            return null;
        }));

        $bag->flow->start(
            new AsyncFlow($fetch),
            $epic,
            'Listing EPIC frames for '.($date ?? 'the latest day').'…'
        );

        return null;
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return null;
    }
}
