<?php

// Windows open with the toolkit the bridge connects (TOOLKIT_BRIDGE, as config/app.php reads it):
// Surface's own defaults name appkit and gtk, so a Qt build would ask for GTK windows.
// 'stage' is left to Surface's defaults.
return [
    'toolkit' => [
        'mac' => [
            'default' => env('TOOLKIT_BRIDGE', 'appkit'),
        ],
        'linux' => [
            'default' => env('TOOLKIT_BRIDGE', 'gtk'),
        ],
    ],
];
