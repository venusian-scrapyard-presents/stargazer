<?php

namespace App\NutsAndBolts;

use ProjectSaturnStudios\Stargazer\NeoWs\DataObjects\CloseApproach;

/**
 * Close approaches in time order. NeoWs' full date reads "2026-Oct-30 10:00", which sorts
 * months alphabetically, so the order comes from the epoch, or the Y-m-d day without one.
 */
final class ApproachOrder
{
    /**
     * @param CloseApproach|null $approach
     * @return int Milliseconds since the epoch; PHP_INT_MAX (last) when unknown.
     */
    public static function key(?CloseApproach $approach): int
    {
        if (! is_null($approach?->epoch_date_close_approach)) {
            return $approach->epoch_date_close_approach;
        }

        $day = is_null($approach?->close_approach_date) ? false : strtotime($approach->close_approach_date.' UTC');

        return $day === false ? PHP_INT_MAX : $day * 1000;
    }
}
