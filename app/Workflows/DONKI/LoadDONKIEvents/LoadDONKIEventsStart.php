<?php

namespace App\Workflows\DONKI\LoadDONKIEvents;

use App\Enums\Outcome;
use App\GlobalVariables;
use App\NutsAndBolts\DonkiRows;
use App\Services\DONKI\DONKIService;
use App\Workflows\Fetch;
use App\Workflows\Report;
use App\Workflows\Validate;
use Voyager\NutsAndBolts\Collection;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

class LoadDONKIEventsStart extends Node
{
    public function __construct(
        protected DONKIService $donki
    ){
        parent::__construct();
    }

    public function prep(SharedBag $shared): mixed
    {
        return [
            $shared,
            $this->donki->from(),
            $this->donki->to(),
            $this->donki->types(),
        ];
    }

    public function exec(mixed $prepRes): mixed
    {
        /**
         * @var GlobalVariables $bag
         * @var list<string> $types
         */
        [$bag, $from, $to, $types] = $prepRes;
        $nasa = $bag->nasa;
        $donki = $this->donki;

        $validate = new Validate(fn (): ?string => match (true) {
            is_null($from) || is_null($to) => 'Pick both dates.',
            $from > $to => 'From is after to.',
            default => null,
        });
        $validate->on('invalid')->next(new Report(Outcome::INVALID));

        // The per-type graph: what donki_records holds first; only the days it lacks are fetched,
        // stored, and joined to the rest. A type that fails is noted and the sweep moves on.
        $recall = new RecallDONKIEvents($from, $to);
        $fetch = new Fetch(
            function (SharedBag $bag, array $params) use ($nasa) {
                [$first, $last] = $bag->{"span-{$params['type']}"};

                return $nasa->donki()->{DonkiRows::TYPES[$params['type']]}($first, $last);
            },
            fn (array $params): string => "fetched-{$params['type']}",
        );
        $fetch->on('failed')->next($donki->render(function (GlobalVariables $bag, array $params): ?string {
            $bag->failed = [...($bag->failed ?? []), $params['type']];

            return null;
        }));
        $limited = $donki->render(function (GlobalVariables $bag, array $params): ?string {
            $bag->failed = [...($bag->failed ?? []), $params['type']];
            $bag->rate_limited ??= $bag->error;

            return null;
        });
        $fetch->on('rate_limited')->next($limited);

        // Once one type hits the hourly limit, the rest of the sweep would only hit it too.
        $quota = new Validate(fn (GlobalVariables $bag): ?string => $bag->rate_limited ?? null);
        $quota->on('invalid')->next($limited);
        $quota->next($fetch);
        $append = $donki->render(function (GlobalVariables $bag, array $params) use ($donki): ?string {
            /** @var Collection $found */
            $found = $bag->{"events-{$params['type']}"};
            $donki->appendEvents($params['type'], $found);

            return null;
        });
        $recall->on('stored')->next($append);
        $recall->on('fetch')->next($quota);
        $fetch->next(new ArchiveDONKIEvents())->next($append);

        $validate->next($donki->render(function (GlobalVariables $bag) use ($donki): ?string {
            // The bag outlives runs: each sweep starts with no failures noted.
            $bag->failed = [];
            $bag->rate_limited = null;
            $donki->clearEvents();

            return null;
        }))->next(new DONKISweep($recall, $types))->next($donki->render(function (GlobalVariables $bag) use ($donki, $types, $from, $to): ?string {
            $donki->finishEvents($bag, $types, $from, $to);

            return null;
        }));

        $bag->flow->start(
            new AsyncFlow($validate),
            $donki,
            count($types) === 1 ? "Fetching {$types[0]} events…" : 'Sweeping every DONKI event type…'
        );

        return null;
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return null;
    }
}
