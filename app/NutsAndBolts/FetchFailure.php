<?php

namespace App\NutsAndBolts;

use App\Enums\Outcome;
use ProjectSaturnStudios\Stargazer\Exceptions\StargazerException;
use Throwable;
use Voyager\Contracts\IOPools\Promise;

/**
 * A request that ended without a result, carried as a value so the graph can route on it.
 */
final readonly class FetchFailure
{
    /** The only host the NASA_API_KEY quota covers. */
    protected const string KEYED_HOST = 'api.nasa.gov';

    public function __construct(
        public Throwable $error,
        public ?int $status,
        public ?string $host = null,
    ) {}

    /**
     * @param Throwable $error
     * @param string|null $url What was asked for; its host names the failure.
     * @return self
     */
    public static function from(Throwable $error, ?string $url = null): self
    {
        return new self(
            $error,
            $error instanceof StargazerException ? $error->status() : null,
            is_null($url) ? null : (parse_url($url, PHP_URL_HOST) ?: null),
        );
    }

    /**
     * Settle a 4xx as a failure value, so the node does not retry it (retrying cannot fix it,
     * and every try spends quota); anything else rejects, so it does.
     * @param Promise $promise
     * @param string|null $url
     * @return Promise
     */
    public static function finalOn4xx(Promise $promise, ?string $url): Promise
    {
        return $promise->error(function (Throwable $e) use ($url): self {
            $failure = self::from($e, $url);

            if ($failure->isFinal()) {
                return $failure;
            }

            throw $e;
        });
    }

    public function isFinal(): bool
    {
        return ! is_null($this->status) && $this->status < 500;
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }

    /**
     * The action the graph follows: 'rate_limited' for a 429, else 'failed'.
     * @return string
     */
    public function route(): string
    {
        return $this->isRateLimited() ? 'rate_limited' : 'failed';
    }

    /**
     * What the status row says. Never the raw transport message: curl appends the full URL,
     * api_key included.
     * @return string
     */
    public function message(): string
    {
        if ($this->isRateLimited()) {
            return in_array($this->host, [null, self::KEYED_HOST], true)
                ? Outcome::RATE_LIMITED->defaultMessage()
                : "{$this->host} is rate-limiting requests; try again in a while.";
        }

        if (! is_null($this->status)) {
            return ($this->host ?? 'NASA')." answered {$this->status}.";
        }

        $message = self::redact($this->error->getMessage());
        if (preg_match('/^cURL error (\d+): (.*?)\s*\(see [^)]*\)/', $message, $match)) {
            return sprintf('Could not reach %s (cURL error %s: %s).', $this->host ?? 'NASA', $match[1], $match[2]);
        }

        return $message;
    }

    protected static function redact(string $message): string
    {
        return preg_replace('/([?&]api_key=)[^&\s]+/', '$1***', $message) ?? $message;
    }
}
