<?php

use App\NutsAndBolts\Primary;
use Surface\Contracts\HumanInput\Modifiers;

it('reads Command on macOS and Control elsewhere as Primary', function () {
    expect(Primary::held(new Modifiers(meta: true), 'Darwin'))->toBeTrue()
        ->and(Primary::held(new Modifiers(ctrl: true), 'Darwin'))->toBeFalse()
        ->and(Primary::held(new Modifiers(ctrl: true), 'Linux'))->toBeTrue()
        ->and(Primary::held(new Modifiers(meta: true), 'Linux'))->toBeFalse();
});
