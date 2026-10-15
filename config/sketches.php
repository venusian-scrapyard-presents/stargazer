<?php

return [
    // Hz. A sketch with a non-null refresh rate overwrites this for its run.
    'refresh_rate' => (float) env('SKETCH_REFRESH_RATE', 60),

    // Extra sketch classes outside the discovered paths.
    'load' => [
        // \App\Other\MyAttributedSketch::class,
    ],
];
