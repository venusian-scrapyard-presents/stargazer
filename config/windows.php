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
// Re-runs the shown screen; greyed while it runs, and on the key screen.
$view = ['label' => 'View', 'items' => [
    ['id' => 'refresh', 'label' => 'Refresh', 'keys' => 'Primary+R'],
]];

return [
    'about' => [
        'name' => env('APP_NAME', 'Venusian'),
        'version' => env('APP_VERSION'),
        'copyright' => \App\NutsAndBolts\Links::credit((int) date('Y')),
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
            $view,
        ],
        'apod' => [
            ['label' => 'App', 'items' => [
                ['role' => 'about', 'label' => 'About'],
                ['separator' => true],
                ['role' => 'quit', 'label' => 'Quit', 'hotkey' => 'q'],
            ]],
            $edit,
            $view,
            ['label' => 'APOD', 'items' => [
                ['id' => 'grid', 'label' => 'Show Grid', 'toggle' => true, 'on' => false],
            ]],
        ],
        'apod-gallery' => [
            ['label' => 'App', 'items' => [
                ['role' => 'about', 'label' => 'About'],
                ['separator' => true],
                ['role' => 'quit', 'label' => 'Quit', 'hotkey' => 'q'],
            ]],
            $edit,
            $view,
            ['label' => 'APOD', 'items' => [
                ['id' => 'single', 'label' => 'Show Single Date', 'toggle' => true, 'on' => false],
            ]],
        ]
    ],
];
