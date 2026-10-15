<?php

namespace App\Services\InSight;

use App\Concerns\InSight\InSightContent;
use App\GlobalVariables;
use App\Services\NasaService;
use ProjectSaturnStudios\Stargazer\InSight\DataObjects\InsightSol;
use ProjectSaturnStudios\Stargazer\InSight\DataObjects\InsightWeather;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\FontWeight;
use Surface\NutsAndBolts\Color;

/**
 * InSight's last week of Martian weather as a grid of daily cards.
 */
class InSightService extends NasaService
{
    use InSightContent;

    protected const int COLUMNS = 4;

    public function key(): string
    {
        return 'insight';
    }

    public function mounted(TKColumn $root): void
    {
        $this->setMainComponents($root);
    }

    /**
     * The weather landed: a card per sol, and the outcome.
     */
    public function weatherLoaded(GlobalVariables $bag): void
    {
        /** @var InsightWeather $weather */
        $weather = $bag->insight_weather;
        $this->cards($weather->sols->all());

        $weather->sols->isEmpty()
            ? $this->empty($bag, 'InSight returned no sols.')
            : $this->done($bag, "{$weather->sols->count()} sols of Mars weather.");
    }

    /**
     * @param list<InsightSol> $sols
     */
    protected function cards(array $sols): void
    {
        foreach ($this->sols->children() as $card) {
            $card->remove();
        }

        foreach (array_values($sols) as $i => $sol) {
            $this->sols->at(intdiv($i, self::COLUMNS), $i % self::COLUMNS);
            $card = $this->sols->column("sol-{$sol->sol}", 4, 10);
            $card->setBackground(Color::hex('#2b1d16'))->minSize(170, 0);

            $card->label('sol', "Sol {$sol->sol}")
                ->setFont(new FontSpec(15, FontWeight::BOLD))
                ->setTextColor(Color::hex('#f2e6dc'));
            $card->label('season', ucfirst($sol->season?->value ?? 'unknown season'))
                ->setTextColor(Color::hex('#c8b4a6'));
            $card->label('high', is_null($sol->temperature) ? 'High —' : sprintf('High %.1f °C', $sol->temperature->max))
                ->setTextColor(Color::hex('#ff9a62'));
            $card->label('low', is_null($sol->temperature) ? 'Low —' : sprintf('Low %.1f °C', $sol->temperature->min))
                ->setTextColor(Color::hex('#8ecbff'));
            $card->label('pressure', is_null($sol->pressure) ? 'Pressure —' : sprintf('%.0f Pa', $sol->pressure->average))
                ->setTextColor(Color::hex('#f2e6dc'));
        }
    }
}
