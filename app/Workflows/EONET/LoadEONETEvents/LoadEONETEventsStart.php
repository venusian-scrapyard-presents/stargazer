<?php

namespace App\Workflows\EONET\LoadEONETEvents;

use App\GlobalVariables;
use App\Services\EONET\EONETService;
use App\Workflows\Fetch;
use ProjectSaturnStudios\Stargazer\EONET\DataObjects\EonetCategory;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

/**
 * Loads the events for the form's category and status. The first load fetches the categories
 * for the dropdown first: two fetches, one flow.
 */
class LoadEONETEventsStart extends Node
{
    public function __construct(
        protected EONETService $eonet
    ){
        parent::__construct();
    }

    public function prep(SharedBag $shared): mixed
    {
        return [$shared, $this->eonet->openOnly(), $this->eonet->pickedCategory(), $this->eonet->hasCategories()];
    }

    public function exec(mixed $prepRes): mixed
    {
        /**
         * @var GlobalVariables $bag
         * @var bool $open
         * @var EonetCategory|null $category
         * @var bool $has_categories
         */
        [$bag, $open, $category, $has_categories] = $prepRes;
        $nasa = $bag->nasa;
        $eonet = $this->eonet;

        $events = new Fetch(
            fn () => $nasa->eonet()->events()->status($open ? 'open' : 'all')->category($category?->id)->limit(EONETService::LIMIT),
            'eonet_events',
        );
        $eonet->failures($events);
        $events->next($eonet->render(function (GlobalVariables $bag) use ($eonet, $category): ?string {
            $eonet->eventsLoaded($bag, $category);

            return null;
        }));

        if ($has_categories) {
            $bag->flow->start(new AsyncFlow($events), $eonet, 'Fetching events…');

            return null;
        }

        $categories = new Fetch(fn () => $nasa->eonet()->categories(), 'eonet_categories');
        $eonet->failures($categories);
        $categories->next($eonet->render(function (GlobalVariables $bag) use ($eonet): ?string {
            $eonet->categoriesLoaded($bag);

            return null;
        }))->next($events);

        $bag->flow->start(new AsyncFlow($categories), $eonet, 'Fetching categories and events…');

        return null;
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return null;
    }
}
