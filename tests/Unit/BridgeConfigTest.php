<?php

it('opens windows with the toolkit TOOLKIT_BRIDGE names, as the bridge connects it', function () {
    $before = getenv('TOOLKIT_BRIDGE');
    putenv('TOOLKIT_BRIDGE=qt');
    $_ENV['TOOLKIT_BRIDGE'] = $_SERVER['TOOLKIT_BRIDGE'] = 'qt';

    try {
        $bridge = require __DIR__.'/../../config/bridge.php';

        expect($bridge['toolkit']['linux']['default'])->toBe('qt')
            ->and($bridge['toolkit']['mac']['default'])->toBe('qt');
    } finally {
        $before === false ? putenv('TOOLKIT_BRIDGE') : putenv("TOOLKIT_BRIDGE={$before}");
        unset($_ENV['TOOLKIT_BRIDGE'], $_SERVER['TOOLKIT_BRIDGE']);
    }
});
