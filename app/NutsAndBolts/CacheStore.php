<?php

namespace App\NutsAndBolts;

use App\Models\PersistentVariable;
use ReflectionException;
use Throwable;
use Voyager\Redis\RedisManager;

/**
 * The cache store the app runs on, kept in persistent_variables: file (the default) or Redis
 * with the connection Settings supplies. The choice cannot live in the cache it picks.
 */
final class CacheStore
{
    public const string VARIABLE = 'cache.store';

    public const string FILE = 'file';

    public const string REDIS = 'redis';

    /** Settings' Redis fields start here: a server on this machine, the cache's own database. */
    public const array REDIS_DEFAULTS = ['host' => 'localhost', 'port' => 6379, 'password' => '', 'database' => 1];

    /** Seconds a Redis connect or read may take before the store counts as unreachable. */
    public const float TIMEOUT = 2.0;

    /** Why the stored Redis did not answer at launch, so the app runs on file; null otherwise. */
    public static ?string $unreachable = null;

    /**
     * Launch onto the stored choice. A stored Redis that does not answer leaves the app on file
     * for this run, with the reason kept for Settings; the stored choice stays as it was.
     * @throws ReflectionException
     */
    public static function start(): void
    {
        $settings = self::stored();
        self::$unreachable = $settings['driver'] === self::REDIS ? self::probe($settings) : null;

        self::apply(is_null(self::$unreachable) ? $settings : ['driver' => self::FILE] + $settings);
    }

    /**
     * The stored choice, or file with the Redis defaults when nothing is stored.
     * @return array{driver: string, host: string, port: int, password: string, database: int}
     * @throws ReflectionException
     */
    public static function stored(): array
    {
        $stored = PersistentVariable::recall(self::VARIABLE);

        return self::normalize(is_array($stored) ? $stored : []);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{driver: string, host: string, port: int, password: string, database: int}
     */
    public static function normalize(array $settings): array
    {
        $host = trim((string) ($settings['host'] ?? ''));
        $port = filter_var($settings['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        $database = filter_var($settings['database'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return [
            'driver' => ($settings['driver'] ?? null) === self::REDIS ? self::REDIS : self::FILE,
            'host' => $host === '' ? self::REDIS_DEFAULTS['host'] : $host,
            'port' => $port === false ? self::REDIS_DEFAULTS['port'] : $port,
            'password' => (string) ($settings['password'] ?? ''),
            'database' => $database === false ? self::REDIS_DEFAULTS['database'] : $database,
        ];
    }

    /**
     * Check that Redis answers on these settings before the app moves onto it.
     * @param array{host: string, port: int, password: string, database: int} $settings
     * @return string|null why it did not answer; null when it did.
     */
    public static function probe(array $settings): ?string
    {
        try {
            $manager = new RedisManager(app(), config('database.redis.client', 'phpredis'), [
                'options' => config('database.redis.options', []),
                'probe' => self::connection($settings, retries: 0),
            ]);
            $manager->connection('probe')->command('ping');
            $manager->purge('probe');

            return null;
        } catch (Throwable $e) {
            return $e->getMessage() === '' ? get_class($e) : $e->getMessage();
        }
    }

    /**
     * Store the choice and switch the running app onto it.
     * @param array<string, mixed> $settings
     * @throws ReflectionException
     */
    public static function save(array $settings): void
    {
        $settings = self::normalize($settings);
        PersistentVariable::query()->updateOrCreate(['key' => self::VARIABLE], ['value' => $settings]);
        self::apply($settings);
        self::$unreachable = null;
    }

    /** The store the running app uses. */
    public static function active(): string
    {
        return config('cache.default') === self::REDIS ? self::REDIS : self::FILE;
    }

    /**
     * Point config at the choice and drop what was built on the old one: the Redis manager
     * copies its connections at construction, and the cache manager keeps each store it built.
     * @param array{driver: string, host: string, port: int, password: string, database: int} $settings
     */
    public static function apply(array $settings): void
    {
        $config = app('config');
        $config->set('cache.default', $settings['driver']);

        if ($settings['driver'] === self::REDIS) {
            // The store reads through 'cache' and locks through 'default': both follow Settings.
            $config->set('database.redis.cache', self::connection($settings));
            $config->set('database.redis.default', self::connection($settings));
            $redis = $config->get('database.redis', []);
            app()->registerInstance('redis', new RedisManager(app(), $redis['client'] ?? 'phpredis', array_diff_key($redis, ['client' => true])));
        }

        app('cache')->forgetDriver([self::FILE, self::REDIS]);
    }

    /**
     * @param array{host: string, port: int, password: string, database: int} $settings
     * @return array<string, mixed>
     */
    protected static function connection(array $settings, int $retries = 1): array
    {
        return [
            'host' => $settings['host'],
            'port' => $settings['port'],
            'password' => $settings['password'] === '' ? null : $settings['password'],
            'database' => $settings['database'],
            'timeout' => self::TIMEOUT,
            'read_timeout' => self::TIMEOUT,
            'max_retries' => $retries,
        ];
    }
}
