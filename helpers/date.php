<?php

if(!function_exists("today")){
    /**
     * @throws DateMalformedStringException
     * @throws ReflectionException|DateInvalidTimeZoneException
     */
    function today(): DateTimeImmutable
    {
        $timezone = config('app.timezone');
        return new DateTimeImmutable('today', new DateTimeZone($timezone));
    }
}
