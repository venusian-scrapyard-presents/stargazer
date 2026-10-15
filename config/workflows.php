<?php

return [
    // 'loop' = the app's event loop; a driver added with AsyncRuntimeManager::extend() by its name
    'runtime' => env('WORKFLOWS_RUNTIME', 'loop'),
];
