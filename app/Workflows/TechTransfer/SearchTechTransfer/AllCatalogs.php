<?php

namespace App\Workflows\TechTransfer\SearchTechTransfer;

use ProjectSaturnStudios\Stargazer\TechTransfer\Enums\TechTransferCatalog;
use Voyager\Workflows\AsyncParallelBatchFlow;
use Voyager\Workflows\BaseNode;
use Voyager\Workflows\SharedBag;

/**
 * One search across several TechTransfer catalogs at once: the inner graph runs once per
 * catalog, all in flight, each branch on its own copy of the nodes and all of them writing
 * into the one bag.
 */
class AllCatalogs extends AsyncParallelBatchFlow
{
    /**
     * @param BaseNode $start The per-catalog graph; it reads params['catalog'].
     * @param list<TechTransferCatalog> $catalogs
     */
    public function __construct(BaseNode $start, protected array $catalogs)
    {
        parent::__construct($start, concurrency: 3);
    }

    /**
     * @param SharedBag $shared
     * @return list<array{catalog: TechTransferCatalog}>
     */
    public function prepAsync(SharedBag $shared): mixed
    {
        return array_map(fn (TechTransferCatalog $catalog): array => ['catalog' => $catalog], $this->catalogs);
    }

    public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
    {
        return null;
    }
}
