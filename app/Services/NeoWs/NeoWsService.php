<?php

namespace App\Services\NeoWs;

use App\Concerns\NeoWs\NeoWsContent;
use App\Enums\Outcome;
use App\GlobalVariables;
use App\NutsAndBolts\ApproachOrder;
use App\Services\NasaService;
use DateTimeImmutable;
use ProjectSaturnStudios\Stargazer\NeoWs\DataObjects\CloseApproach;
use ProjectSaturnStudios\Stargazer\NeoWs\DataObjects\NearEarthObject;
use ProjectSaturnStudios\Stargazer\NeoWs\DataObjects\NeoFeed;
use Surface\Contracts\Windows\Primitives\TKColumn;

/**
 * Asteroids passing Earth over up to four weeks, asked for a week at a time; filter to the
 * potentially hazardous ones, and look one up for its orbit.
 */
class NeoWsService extends NasaService
{
    use NeoWsContent;

    /** @var list<array{0: NearEarthObject, 1: CloseApproach|null}> Every object loaded, by approach date. */
    protected array $all = [];

    /** @var list<int> Table row => index into $all, after the hazard filter. */
    protected array $shown = [];

    public function key(): string
    {
        return 'neows';
    }

    public function mounted(TKColumn $root): void
    {
        $this->setMainComponents($root);
    }

    public function startDate(): ?DateTimeImmutable
    {
        return $this->start->date();
    }

    public function dayCount(): int
    {
        return (int) round($this->days->value());
    }

    /**
     * The object behind a table row, after the hazard filter; null for no row.
     */
    public function object(?int $row): ?NearEarthObject
    {
        return is_null($row) ? null : ($this->all[$this->shown[$row] ?? -1][0] ?? null);
    }

    public function isLoad(string $uuid): bool
    {
        return $uuid === $this->load?->uuid();
    }

    public function isDays(string $uuid): bool
    {
        return $uuid === $this->days?->uuid();
    }

    public function isHazardous(string $uuid): bool
    {
        return $uuid === $this->hazardous?->uuid();
    }

    public function isObjects(string $uuid): bool
    {
        return $uuid === $this->objects?->uuid();
    }

    public function daysChanged(float $value): bool
    {
        $days = (int) round($value);
        $this->days_label->setText(sprintf('%d day%s', $days, $days === 1 ? '' : 's'));

        return true;
    }

    /**
     * A new load: the table and the orbit cleared.
     */
    public function clearObjects(): void
    {
        $this->all = [];
        $this->paint();
        $this->orbit->setValue('');
    }

    /**
     * A week landed: its objects join the table, which stays in approach order.
     */
    public function weekLanded(NeoFeed $week): void
    {
        foreach ($week->near_earth_objects as $objects) {
            foreach ($objects as $object) {
                $this->all[] = [$object, $object->close_approach_data->first()];
            }
        }
        $this->paint();
    }

    /**
     * Every week answered or failed: record the outcome.
     */
    public function finishLoad(GlobalVariables $bag): void
    {
        $days = $bag->neows_days;
        $note = match (true) {
            $bag->neows_failed_weeks === 0 => '',
            $bag->neows_rate_limited => " ({$bag->neows_failed_weeks} of {$bag->neows_week_count} weeks not loaded: ".Outcome::RATE_LIMITED->defaultMessage().')',
            default => " ({$bag->neows_failed_weeks} of {$bag->neows_week_count} weeks failed)",
        };
        $hazardous = count(array_filter($this->all, fn (array $entry): bool => (bool) $entry[0]->is_potentially_hazardous_asteroid));

        $this->all === []
            ? $this->empty($bag, "No close approaches in those {$days} days{$note}.")
            : $this->done($bag, sprintf('%d objects over %d days, %d potentially hazardous%s.', count($this->all), $days, $hazardous, $note));
    }

    /**
     * A looked-up object landed: its orbit below the table.
     */
    public function showOrbit(GlobalVariables $bag): void
    {
        /** @var NearEarthObject $found */
        $found = $bag->neows_object;
        $orbit = $found->orbital_data;
        $approaches = $found->close_approach_data->count();
        $this->orbit->setValue(implode("\n", array_filter([
            $found->name ?? $found->id,
            is_null($orbit) ? null : sprintf('Orbit class   %s — %s', $orbit->orbit_class?->orbit_class_type ?? '?', $orbit->orbit_class?->orbit_class_description ?? ''),
            is_null($orbit) ? null : sprintf('Period        %s days    eccentricity %s    inclination %s°', $orbit->orbital_period ?? '?', $orbit->eccentricity ?? '?', $orbit->inclination ?? '?'),
            is_null($orbit) ? null : sprintf('Perihelion    %s au      aphelion %s au', $orbit->perihelion_distance ?? '?', $orbit->aphelion_distance ?? '?'),
            is_null($orbit) ? null : sprintf('Observed      %s to %s, %s observations', $orbit->first_observation_date ?? '?', $orbit->last_observation_date ?? '?', $orbit->observations_used ?? '?'),
            "Close approaches on record: {$approaches}",
            $found->nasa_jpl_url,
        ])));
        $this->done($bag, ($found->name ?? $found->id).': '.($orbit?->orbit_class?->orbit_class_type ?? 'orbit unknown').'.');
    }

    /**
     * Sort by closest approach and apply the hazard filter.
     */
    public function paint(): bool
    {
        usort($this->all, fn (array $a, array $b): int => ApproachOrder::key($a[1]) <=> ApproachOrder::key($b[1]));
        $only_hazardous = self::isSwitchedOn($this->hazardous);

        $this->shown = [];
        $rows = [];
        foreach ($this->all as $i => [$object, $approach]) {
            if ($only_hazardous && ! $object->is_potentially_hazardous_asteroid) {
                continue;
            }

            $this->shown[] = $i;
            $meters = $object->estimated_diameter?->meters;
            $rows[] = [
                'date' => $approach?->close_approach_date_full ?? $approach?->close_approach_date ?? '',
                'diameter' => is_null($meters) ? '' : sprintf('%.0f–%.0f', $meters->estimated_diameter_min, $meters->estimated_diameter_max),
                'miss' => is_null($approach?->miss_distance?->kilometers) ? '' : number_format((float) $approach->miss_distance->kilometers),
                'speed' => is_null($approach?->relative_velocity?->kilometers_per_second) ? '' : sprintf('%.1f', (float) $approach->relative_velocity->kilometers_per_second),
                'hazard' => $object->is_potentially_hazardous_asteroid ? 'Yes' : '',
                'name' => $object->name ?? $object->id,
            ];
        }

        $this->objects->setRows($rows);

        return true;
    }

    /**
     * The table only changes between runs, so a row click can never start a lookup that would
     * leave the week-by-week load stale.
     */
    protected function setBusy(bool $busy): void
    {
        $this->load->setEnabled(! $busy);
        $this->objects->setEnabled(! $busy);
    }
}
