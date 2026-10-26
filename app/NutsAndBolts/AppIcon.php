<?php

namespace App\NutsAndBolts;

use Throwable;

/**
 * The app icon as a plain file. A packaged app reads its own files through phar://, which the
 * toolkits' image loaders cannot open, so the icon is copied out to storage once.
 */
final class AppIcon
{
    public const string SOURCE = 'art/icon-128.png';

    public const string COPY = 'app/icon-128.png';

    /** The copy's path, made when it is missing; null when the icon cannot be read or written. */
    public static function path(): ?string
    {
        return self::file(base_path(self::SOURCE), storage_path(self::COPY));
    }

    /**
     * $destination, copied from $source when it is not there yet; null when the copy cannot be
     * made.
     */
    public static function file(string $source, string $destination): ?string
    {
        if (is_file($destination)) {
            return $destination;
        }
        if (! is_file($source)) {
            return null;
        }

        try {
            $bytes = file_get_contents($source);
            $directory = dirname($destination);
            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            return $bytes !== false && file_put_contents($destination, $bytes) !== false ? $destination : null;
        } catch (Throwable) {
            return null;
        }
    }
}
