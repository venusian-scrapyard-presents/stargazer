<?php

namespace App\Workflows\DONKI\LoadDONKIEvents;

use Voyager\Workflows\AsyncBatchFlow;
use Voyager\Workflows\BaseNode;
use Voyager\Workflows\SharedBag;

/**
 * DONKI's event types swept one after another over the same dates: the inner graph runs once
 * per type, in order. Sequential on purpose — CCMC's service gets one request at a time, a 429
 * can stop the rest of the sweep before they are sent, and the table fills type by type.
 */
class DONKISweep extends AsyncBatchFlow
{
    /**
     * @param BaseNode $start The per-type graph; it reads params['type'].
     * @param list<string> $types DonkiRows::TYPES keys.
     */
    public function __construct(BaseNode $start, protected array $types)
    {
        parent::__construct($start);
    }

    /**
     * @return list<array{type: string}>
     */
    public function prepAsync(SharedBag $shared): mixed
    {
        return array_map(fn (string $type): array => ['type' => $type], $this->types);
    }

    public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed
    {
        return null;
    }
}
