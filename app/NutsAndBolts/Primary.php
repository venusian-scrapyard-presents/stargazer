<?php

namespace App\NutsAndBolts;

use Surface\Contracts\HumanInput\Modifiers;

/**
 * The platform's command modifier, as Surface's `Primary` accelerator token means it: Command on
 * macOS, Control elsewhere. Every driver reports Command as meta.
 */
final class Primary
{
    public static function held(Modifiers $modifiers, string $os = PHP_OS_FAMILY): bool
    {
        return $os === 'Darwin' ? $modifiers->meta : $modifiers->ctrl;
    }
}
