<?php

namespace App;

use ReflectionException;

class Route
{
    /**
     * @throws ReflectionException
     */
    public static function controller(string $controller, callable $make): void
    {
        app()->registerSingleton($controller, $make);
    }
}
