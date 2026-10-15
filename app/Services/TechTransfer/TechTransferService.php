<?php

namespace App\Services\TechTransfer;

use App\Concerns\TechTransfer\TechTransferContent;
use App\Enums\Outcome;
use App\GlobalVariables;
use App\Services\NasaService;
use ProjectSaturnStudios\Stargazer\TechTransfer\DataObjects\TechTransferPage;
use ProjectSaturnStudios\Stargazer\TechTransfer\DataObjects\TechTransferRecord;
use ProjectSaturnStudios\Stargazer\TechTransfer\Enums\TechTransferCatalog;
use Surface\Contracts\Windows\Primitives\TKColumn;

/**
 * NASA's patents, software and spinoffs: one catalog, or all three searched side by side.
 */
class TechTransferService extends NasaService
{
    use TechTransferContent;

    /** Dropdown entries; index 0 searches every catalog. */
    protected const array CATALOGS = ['All catalogs', 'Patents', 'Software', 'Spinoffs'];

    /** @var list<array{0: TechTransferCatalog, 1: TechTransferRecord}> The table's rows, in order. */
    protected array $records = [];

    public function key(): string
    {
        return 'techtransfer';
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
     * The picked catalog, or every catalog for All catalogs.
     * @return list<TechTransferCatalog>
     */
    public function catalogs(): array
    {
        $selected = $this->catalog->selected();

        return $selected === 0 ? TechTransferCatalog::cases() : [TechTransferCatalog::cases()[$selected - 1]];
    }

    public function isQuery(string $uuid): bool
    {
        return $uuid === $this->query?->uuid();
    }

    public function isSearch(string $uuid): bool
    {
        return $uuid === $this->search?->uuid();
    }

    public function isResults(string $uuid): bool
    {
        return $uuid === $this->results?->uuid();
    }

    /**
     * A new search: no rows, no abstract, no failures noted (the bag outlives runs).
     */
    public function clearResults(GlobalVariables $bag): void
    {
        $bag->techtransfer_failed = [];
        $bag->techtransfer_rate_limited = null;
        $this->records = [];
        $this->results->clearRows();
        $this->abstract->setValue('');
    }

    /**
     * One catalog's page landed: its rows join the table.
     */
    public function appendPage(GlobalVariables $bag, TechTransferCatalog $catalog): void
    {
        /** @var TechTransferPage $page */
        $page = $bag->{"techtransfer_page_{$catalog->value}"};
        foreach ($page->results as $record) {
            $this->records[] = [$catalog, $record];
            $this->results->appendRow([
                'catalog' => ucfirst($catalog->value),
                'title' => self::plain($record->title),
                'center' => $record->center,
                'category' => $record->category,
            ]);
        }
    }

    /**
     * A catalog failed; with $limited, NASA rate-limited it.
     */
    public function catalogFailed(GlobalVariables $bag, TechTransferCatalog $catalog, bool $limited): void
    {
        $bag->techtransfer_failed = [...($bag->techtransfer_failed ?? []), $catalog->name];
        if ($limited) {
            $bag->techtransfer_rate_limited = $bag->error;
        }
    }

    /**
     * Every catalog answered or failed: record the outcome.
     * @param list<TechTransferCatalog> $catalogs
     */
    public function finishSearch(GlobalVariables $bag, string $query, array $catalogs): void
    {
        $failed = $bag->techtransfer_failed ?? [];
        $limited = $bag->techtransfer_rate_limited ?? null;
        if (count($failed) === count($catalogs)) {
            $bag->outcome = is_null($limited)
                ? [Outcome::FAILED, 'Every catalog failed: '.implode(', ', $failed).'.']
                : [Outcome::RATE_LIMITED, $limited];

            return;
        }

        $note = match (true) {
            $failed === [] => '',
            ! is_null($limited) => ' ('.implode(', ', $failed)." not loaded: {$limited})",
            default => ' ('.implode(', ', $failed).' failed)',
        };
        $this->records === []
            ? $this->empty($bag, "Nothing matches “{$query}”{$note}.")
            : $this->done($bag, sprintf('%d results for “%s” across %d catalog%s%s.', count($this->records), $query, count($catalogs), count($catalogs) === 1 ? '' : 's', $note));
    }

    /**
     * A table row picked: its record's title, case and abstract below the table.
     */
    public function select(?int $row): bool
    {
        [$catalog, $record] = is_null($row) ? [null, null] : ($this->records[$row] ?? [null, null]);
        if (! is_null($record)) {
            $this->abstract->setValue(self::plain($record->title)."\n{$record->caseNumber} · {$record->center} · ".ucfirst($catalog->value)."\n\n".self::plain($record->description));
        }

        return true;
    }

    protected function setBusy(bool $busy): void
    {
        $this->search->setEnabled(! $busy);
    }
}
