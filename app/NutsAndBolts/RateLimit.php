<?php

namespace App\NutsAndBolts;

use Closure;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Voyager\Contracts\Cache\Repository;

/**
 * api.nasa.gov's hourly quota, read off every response that carries it and kept in the cache
 * per key. NASA counts a rolling hour, so a reading older than an hour says nothing and expires.
 */
final class RateLimit
{
    public const string PREFIX = 'nasa.rate_limit.';

    public const int TTL = 3600;

    /**
     * @param Closure $cache (): Repository — asked on each record, so a store switch takes effect.
     */
    public function __construct(protected Closure $cache) {}

    /**
     * Guzzle middleware for the app's Http factory: passes every response through untouched.
     * @return Closure
     */
    public function middleware(): Closure
    {
        return fn (callable $handler) => fn (RequestInterface $request, array $options) => $handler($request, $options)
            ->then(function (ResponseInterface $response) use ($request): ResponseInterface {
                // A cache that cannot take the reading never costs NASA's answer.
                try {
                    $this->record($request, $response);
                } catch (Throwable $e) {
                    report($e);
                }

                return $response;
            });
    }

    public function record(RequestInterface $request, ResponseInterface $response): void
    {
        $limit = $response->getHeaderLine('X-RateLimit-Limit');
        $remaining = $response->getHeaderLine('X-RateLimit-Remaining');
        parse_str($request->getUri()->getQuery(), $query);
        $key = $query['api_key'] ?? null;

        if (! is_numeric($limit) || ! is_numeric($remaining) || ! is_string($key) || $key === '') {
            return;
        }

        $this->cache()->put(self::cacheKey($key), [
            'limit' => (int) $limit,
            'remaining' => (int) $remaining,
            'at' => time(),
        ], self::TTL);
    }

    /**
     * @return array{limit: int, remaining: int, at: int}|null
     */
    public function reading(string $key): ?array
    {
        $reading = $this->cache()->get(self::cacheKey($key));

        return is_array($reading) ? $reading : null;
    }

    /**
     * One line for Settings.
     * @param array{limit: int, remaining: int, at: int}|null $reading
     */
    public static function describe(?array $reading): string
    {
        if (is_null($reading)) {
            return 'No api.nasa.gov request with this key in the last hour.';
        }

        return sprintf(
            '%s of %s requests left this hour, as of %s.',
            number_format($reading['remaining']),
            number_format($reading['limit']),
            date('H:i:s', $reading['at']),
        );
    }

    public static function cacheKey(string $key): string
    {
        return self::PREFIX.hash('sha256', $key);
    }

    protected function cache(): Repository
    {
        return ($this->cache)();
    }
}
