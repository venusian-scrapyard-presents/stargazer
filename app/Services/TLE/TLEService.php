<?php

namespace App\Services\TLE;

use App\Concerns\TLE\TLEContent;
use App\GlobalVariables;
use App\Services\NasaService;
use ProjectSaturnStudios\Stargazer\TLE\DataObjects\TleCollection;
use ProjectSaturnStudios\Stargazer\TLE\DataObjects\TleRecord;
use Surface\Contracts\Windows\Primitives\TKColumn;

/**
 * Two-line orbital elements: search satellites, pick one, read its freshest TLE.
 */
class TLEService extends NasaService
{
    use TLEContent;

    /** @var list<TleRecord> The table's rows, in order. */
    protected array $records = [];

    public function key(): string
    {
        return 'tle';
    }

    public function mounted(TKColumn $root): void
    {
        $this->setMainComponents($root);
    }

    public function query(): string
    {
        return trim($this->query->value());
    }

    /**
     * The record behind a table row; null for no row, or one the table no longer has.
     */
    public function record(?int $row): ?TleRecord
    {
        return is_null($row) ? null : ($this->records[$row] ?? null);
    }

    public function isQuery(string $uuid): bool
    {
        return $uuid === $this->query?->uuid();
    }

    public function isSearch(string $uuid): bool
    {
        return $uuid === $this->search?->uuid();
    }

    public function isSatellites(string $uuid): bool
    {
        return $uuid === $this->satellites?->uuid();
    }

    /**
     * A search landed: one row per satellite, the elements cleared, and the outcome.
     */
    public function showResults(GlobalVariables $bag, string $query): void
    {
        /** @var TleCollection $found */
        $found = $bag->tle_found;
        $this->records = $found->members->values()->all();
        $this->satellites->setRows(array_map(fn (TleRecord $record): array => [
            'id' => $record->satelliteId,
            'name' => $record->name,
            'epoch' => $record->date,
        ], $this->records));
        $this->elements->setValue('');

        $this->records === []
            ? $this->empty($bag, "No satellite matches “{$query}”.")
            : $this->done($bag, sprintf('%d of %d satellites%s.', count($this->records), $found->totalItems, $query === '' ? '' : " matching “{$query}”"));
    }

    /**
     * A satellite's fresh elements landed.
     */
    public function showElements(GlobalVariables $bag): void
    {
        /** @var TleRecord $satellite */
        $satellite = $bag->tle_satellite;
        $this->elements->setValue("{$satellite->name}\n{$satellite->line1}\n{$satellite->line2}");
        $this->done($bag, "{$satellite->name}, elements from {$satellite->date}.");
    }

    protected function setBusy(bool $busy): void
    {
        $this->search->setEnabled(! $busy);
        $this->satellites->setEnabled(! $busy);
    }
}
