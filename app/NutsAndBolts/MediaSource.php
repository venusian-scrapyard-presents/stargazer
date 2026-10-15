<?php

namespace App\NutsAndBolts;

use Closure;

/**
 * Where a picture or video lives, and how to fetch it on the loop.
 */
final readonly class MediaSource
{
    /**
     * @param string $url The file's URL; also its cache key.
     * @param Closure|null $fetch (): Promise<Response> — a DTO's own render()/fetch(), whose body
     *                            arrives in memory (pictures). null: the cache streams the URL
     *                            to disk (videos, anything large).
     */
    public function __construct(
        public string $url,
        public ?Closure $fetch = null,
    ) {}

    /**
     * Streamed straight to disk by the cache.
     * @param string $url
     * @return self
     */
    public static function of(string $url): self
    {
        return new self($url);
    }
}
