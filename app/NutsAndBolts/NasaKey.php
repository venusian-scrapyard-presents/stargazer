<?php

namespace App\NutsAndBolts;

use App\GlobalVariables;
use App\Models\PersistentVariable;
use ProjectSaturnStudios\Stargazer\NasaClient;
use ReflectionException;

/**
 * The api.nasa.gov key, kept in persistent_variables. The NASA client bakes its key in when it
 * is built, so applying a key builds a new client and binds it in place of the old one.
 */
final class NasaKey
{
    public const string VARIABLE = 'nasa.api_key';

    /** NASA's shared demo key: works everywhere, with low rate limits. */
    public const string DEFAULT = 'DEMO_KEY';

    /**
     * @throws ReflectionException
     */
    public static function stored(): ?string
    {
        $key = PersistentVariable::recall(self::VARIABLE);

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Store the key and switch the running client to it.
     * @throws ReflectionException
     */
    public static function save(string $key): NasaClient
    {
        PersistentVariable::query()->updateOrCreate(['key' => self::VARIABLE], ['value' => $key]);

        return self::apply($key);
    }

    /**
     * Build a client on $key and bind it as 'nasa' and on the bag.
     * @throws ReflectionException
     */
    public static function apply(string $key): NasaClient
    {
        app('config')->set('nasa.api_key', $key);
        $client = new NasaClient(api_key: $key, http: app('http'));
        app()->registerInstance('nasa', $client);
        GlobalVariables::make()->nasa = $client;

        return $client;
    }
}
