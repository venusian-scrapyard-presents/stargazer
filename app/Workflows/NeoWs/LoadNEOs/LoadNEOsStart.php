<?php

namespace App\Workflows\NeoWs\LoadNEOs;

use App\Enums\Outcome;
use App\GlobalVariables;
use App\Services\NeoWs\NeoWsService;
use App\Workflows\Report;
use App\Workflows\Validate;
use DateTimeImmutable;
use ProjectSaturnStudios\Stargazer\NeoWs\DataObjects\NeoFeed;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

class LoadNEOsStart extends Node
{
    public function __construct(
        protected NeoWsService $neows
    ){
        parent::__construct();
    }

    public function prep(SharedBag $shared): mixed
    {
        return [$shared, $this->neows->startDate(), $this->neows->dayCount()];
    }

    public function exec(mixed $prepRes): mixed
    {
        /**
         * @var GlobalVariables $bag
         * @var DateTimeImmutable|null $start
         * @var int $days
         */
        [$bag, $start, $days] = $prepRes;
        $neows = $this->neows;

        $validate = new Validate(fn (): ?string => is_null($start) ? 'Pick a start day.' : null);
        $validate->on('invalid')->next(new Report(Outcome::INVALID));

        $weeks = new NeoWsWeeks($bag->nasa, fn (NeoFeed $week) => $neows->weekLanded($week));
        $neows->failures($weeks);

        $validate->next($neows->render(function (GlobalVariables $bag) use ($neows, $start, $days): ?string {
            $bag->neows_start = $start;
            $bag->neows_days = $days;
            $neows->clearObjects();

            return null;
        }))->next($weeks)->next($neows->render(function (GlobalVariables $bag) use ($neows): ?string {
            $neows->finishLoad($bag);

            return null;
        }));

        $bag->flow->start(
            new AsyncFlow($validate),
            $neows,
            "Asking NeoWs about {$days} days, a week at a time…"
        );

        return null;
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return null;
    }
}
