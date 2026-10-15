<?php

// The edit roles run on the focused field with the platform's own keys (⌘V / Ctrl+V).
$edit = ['label' => 'Edit', 'items' => [
    ['role' => 'undo', 'label' => 'Undo'],
    ['role' => 'redo', 'label' => 'Redo'],
    ['separator' => true],
    ['role' => 'cut', 'label' => 'Cut'],
    ['role' => 'copy', 'label' => 'Copy'],
    ['role' => 'paste', 'label' => 'Paste'],
    ['role' => 'select-all', 'label' => 'Select All'],
]];

return [
    'about' => [
        'name' => env('APP_NAME', 'Venusian'),
        'version' => env('APP_VERSION'),
        'copyright' => null,
    ],

    // Shown on macOS when none of our windows has focus. Null = empty bar.
    'default_menu' => 'main',

    'menus' => [
        'main' => [
            ['label' => 'App', 'items' => [
                ['role' => 'about', 'label' => 'About'],
                ['separator' => true],
                ['role' => 'quit', 'label' => 'Quit', 'hotkey' => 'q'],
            ]],
            $edit,
        ],
        'apod' => [
            ['label' => 'App', 'items' => [
                ['role' => 'about', 'label' => 'About'],
                ['separator' => true],
                ['role' => 'quit', 'label' => 'Quit', 'hotkey' => 'q'],
            ]],
            $edit,
            ['label' => 'APOD', 'items' => [
                ['id' => 'grid', 'label' => 'Show Grid', 'toggle' => true, 'on' => false],
            ]],
            //['label' => 'View', 'items' => []],
        ],
        'apod-gallery' => [
            ['label' => 'App', 'items' => [
                ['role' => 'about', 'label' => 'About'],
                ['separator' => true],
                ['role' => 'quit', 'label' => 'Quit', 'hotkey' => 'q'],
            ]],
            $edit,
            ['label' => 'APOD', 'items' => [
                ['id' => 'single', 'label' => 'Show Single Date', 'toggle' => true, 'on' => false],
            ]],
            //['label' => 'View', 'items' => []],
        ]
    ],
];
