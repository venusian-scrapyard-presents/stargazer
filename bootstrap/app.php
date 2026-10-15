<?php

use Voyager\Core\Bootstrap\Exceptions;
use Voyager\Core\VenusianVoyager;

return VenusianVoyager::setup(base_path: dirname(__DIR__))
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->withSketches([__DIR__.'/../app/Runner/Sketches'])
    ->create();
