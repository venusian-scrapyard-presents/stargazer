<?php

namespace App\Concerns;

use App\NutsAndBolts\Busy;
use Surface\Contracts\Windows\Primitives\TKCheckbox;
use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Windows\Primitives\TKToggle;
use Surface\Contracts\Windows\Primitives\TKVideo;
use Surface\Contracts\Windows\WindowException;

/**
 * Primitives a toolkit may not have, with the stand-in the app uses instead. The toolkit
 * decides by throwing; nothing here names one.
 */
trait BuildsWithFallbacks
{
    /**
     * A labelled on/off switch: a toggle with a label before it, or a checkbox carrying the
     * label. Either posts Toggled.
     * @param TKPrimitiveGroup $in
     * @param string $name
     * @param string $label
     * @param bool $on
     * @return TKToggle|TKCheckbox
     */
    protected function switchIn(TKPrimitiveGroup $in, string $name, string $label, bool $on = false): TKToggle|TKCheckbox
    {
        $caption = $in->label("{$name}-label", $label);

        try {
            return $in->toggle($name, $on);
        } catch (WindowException) {
            $caption->remove();

            return $in->checkbox($name, $label, $on);
        }
    }

    /**
     * Whether a switchIn() switch is on, whichever primitive it turned out to be.
     */
    protected static function isSwitchedOn(TKToggle|TKCheckbox $switch): bool
    {
        return $switch instanceof TKToggle ? $switch->isOn() : $switch->isChecked();
    }

    /**
     * A video view, or null where the toolkit has no media runtime (the caller shows a link).
     * @param TKPrimitiveGroup $in
     * @param string $name
     * @return TKVideo|null
     */
    protected function videoIn(TKPrimitiveGroup $in, string $name): ?TKVideo
    {
        try {
            return $in->video($name);
        } catch (WindowException) {
            return null;
        }
    }
}
