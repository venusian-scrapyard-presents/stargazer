<?php

namespace App\Workflows\TechTransfer\SearchTechTransfer;

use App\Enums\Outcome;
use App\GlobalVariables;
use App\Services\TechTransfer\TechTransferService;
use App\Workflows\Fetch;
use App\Workflows\Report;
use App\Workflows\Validate;
use ProjectSaturnStudios\Stargazer\TechTransfer\Enums\TechTransferCatalog;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

class SearchTechTransferStart extends Node
{
    public function __construct(
        protected TechTransferService $techtransfer
    ){
        parent::__construct();
    }

    public function prep(SharedBag $shared): mixed
    {
        return [$shared, $this->techtransfer->query(), $this->techtransfer->catalogs()];
    }

    public function exec(mixed $prepRes): mixed
    {
        /**
         * @var GlobalVariables $bag
         * @var string $query
         * @var list<TechTransferCatalog> $catalogs
         */
        [$bag, $query, $catalogs] = $prepRes;
        $nasa = $bag->nasa;
        $service = $this->techtransfer;

        $validate = new Validate(fn (): ?string => $query === '' ? 'Type something to search for.' : null);
        $validate->on('invalid')->next(new Report(Outcome::INVALID));

        // The per-catalog graph: fetch that catalog's page, then add its rows. A catalog that
        // fails is noted and the others carry on.
        $fetch = new Fetch(
            fn (SharedBag $bag, array $params) => $nasa->techtransfer()->{$params['catalog']->value}($query),
            fn (array $params): string => "techtransfer_page_{$params['catalog']->value}",
        );
        $fetch->on('failed')->next($service->render(function (GlobalVariables $bag, array $params) use ($service): ?string {
            $service->catalogFailed($bag, $params['catalog'], false);

            return null;
        }));
        $fetch->on('rate_limited')->next($service->render(function (GlobalVariables $bag, array $params) use ($service): ?string {
            $service->catalogFailed($bag, $params['catalog'], true);

            return null;
        }));
        $fetch->next($service->render(function (GlobalVariables $bag, array $params) use ($service): ?string {
            $service->appendPage($bag, $params['catalog']);

            return null;
        }));

        $validate->next($service->render(function (GlobalVariables $bag) use ($service): ?string {
            $service->clearResults($bag);

            return null;
        }))->next(new AllCatalogs($fetch, $catalogs))->next($service->render(function (GlobalVariables $bag) use ($service, $query, $catalogs): ?string {
            $service->finishSearch($bag, $query, $catalogs);

            return null;
        }));

        $bag->flow->start(
            new AsyncFlow($validate),
            $service,
            "Searching for “{$query}”…"
        );

        return null;
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return null;
    }
}
