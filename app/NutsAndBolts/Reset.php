<?php

namespace App\NutsAndBolts;

use App\Actions\Work\DispatchController;
use App\GlobalVariables;
use App\Runner\Controllers\StargazerController;
use ReflectionException;
use Surface\Contracts\Windows\Services\Alert;
use Throwable;

/**
 * Clear data and reset: every app table emptied, the caches flushed, the downloaded media
 * removed, the running app back on DEMO_KEY and the file cache, and the welcome screen shown.
 */
final class Reset
{
    /** The migrations ledger stays, so the next launch does not run the migrations again. */
    public const string KEEP = 'migrations';

    /** The button index that means go ahead. */
    public const int CONFIRM = 0;

    /** The alert the Reset button opens. Cancel answers Enter, Esc and the close. */
    public static function alert(): Alert
    {
        return new Alert(
            'Clear all data and reset Stargazer?',
            'This removes the saved NASA key, the cached days and pictures, the cache store and the start page. Stargazer then shows the welcome screen.',
            ['Reset', 'Cancel'],
            default: 1,
            cancel: 1,
        );
    }

    public static function confirmed(int $button): bool
    {
        return $button === self::CONFIRM;
    }

    /**
     * The tables to empty, out of the connection's listing.
     * @param list<string> $listing
     * @return list<string>
     */
    public static function tables(array $listing): array
    {
        return array_values(array_filter($listing, fn (string $table) => $table !== self::KEEP));
    }

    /** @param array{driver: string} $stored the stored cache settings */
    public static function flushesRedis(array $stored): bool
    {
        return $stored['driver'] === CacheStore::REDIS;
    }

    /**
     * Do it. Each step is tried on its own, so one that fails does not stop the rest.
     * @return list<string> what did not clear, as messages; empty when everything did
     * @throws ReflectionException
     */
    public static function run(): array
    {
        $bag = GlobalVariables::make();
        $stored = CacheStore::stored();
        $problems = [];

        try {
            if (self::flushesRedis($stored) && is_null(CacheStore::probe($stored))) {
                app('cache')->store(CacheStore::REDIS)->flush();
            }
        } catch (Throwable $e) {
            $problems[] = "Redis was not flushed: {$e->getMessage()}";
        }

        try {
            app('cache')->store(CacheStore::FILE)->flush();
        } catch (Throwable $e) {
            $problems[] = "The file cache was not flushed: {$e->getMessage()}";
        }

        try {
            $schema = app('db')->connection()->getSchemaBuilder();
            $schema->withoutForeignKeyConstraints(function () use ($schema): void {
                foreach (self::tables($schema->getTableListing(schemaQualified: false)) as $table) {
                    app('db')->table($table)->delete();
                }
            });
        } catch (Throwable $e) {
            $problems[] = "The database was not emptied: {$e->getMessage()}";
        }

        try {
            new MediaCache(storage_path('app/stargazer'), GlobalVariables::runtime())->clear();
        } catch (Throwable $e) {
            $problems[] = "The downloaded pictures were not removed: {$e->getMessage()}";
        }

        CacheStore::apply(CacheStore::normalize([]));
        CacheStore::$unreachable = null;
        NasaKey::apply(NasaKey::DEFAULT);

        /** @var StargazerController $controller */
        foreach ($bag->controllers() as $controller) {
            $controller->reset();
        }
        DispatchController::deploy('key', $bag);

        return $problems;
    }
}
