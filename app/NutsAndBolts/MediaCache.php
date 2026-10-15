<?php

namespace App\NutsAndBolts;

use FilesystemIterator;
use ProjectSaturnStudios\Stargazer\Exceptions\StargazerException;
use Throwable;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Contracts\Workflows\AsyncRuntime;
use Voyager\Http\Client\Factory;
use Voyager\Http\Client\Response;

/**
 * Downloaded pictures and videos, one file per URL, for the image and video views.
 */
final class MediaCache
{
    /** A stalled transfer gives up after LOW_SPEED_TIME seconds under LOW_SPEED_LIMIT bytes/s. */
    protected const int LOW_SPEED_LIMIT = 1024;

    protected const int LOW_SPEED_TIME = 30;

    /** @var array<string, Promise> Local path => the download writing it. */
    protected array $pending = [];

    /**
     * @param string $directory
     * @param AsyncRuntime $runtime
     * @param Factory|null $http For streamed downloads; the app's 'http' when null.
     */
    public function __construct(
        protected string $directory,
        protected AsyncRuntime $runtime,
        protected ?Factory $http = null,
    ) {}

    /**
     * The local file a URL is cached in.
     * @param string $url
     * @return string
     */
    public function path(string $url): string
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return $this->directory.'/'.hash('xxh128', $url).'.'.($extension === '' ? 'bin' : $extension);
    }

    /**
     * Fetch a file once; later calls answer the cached path without a request.
     * @param MediaSource $source
     * @return Promise Fulfils with the local path; rejects with StargazerException on a non-2xx.
     */
    public function fetch(MediaSource $source): Promise
    {
        $path = $this->path($source->url);

        if (is_file($path)) {
            return $this->runtime->resolve($path);
        }

        // Two runs wanting the same file share one download (and one .part file).
        if (isset($this->pending[$path])) {
            return $this->pending[$path];
        }

        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }

        $download = is_null($source->fetch) ? $this->stream($source->url, $path) : $this->hold($source, $path);
        $this->pending[$path] = $download;
        $download->finally(function () use ($path): void {
            unset($this->pending[$path]);
        });

        return $download;
    }

    public function clear(): void
    {
        if (! is_dir($this->directory)) {
            return;
        }

        foreach (new FilesystemIterator($this->directory) as $file) {
            unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    /**
     * Written as it arrives into a .part file, renamed into place once the answer is a 2xx. No
     * total time limit (a large video may take minutes); a transfer that stalls gives up.
     * @param string $url
     * @param string $path
     * @return Promise
     */
    protected function stream(string $url, string $path): Promise
    {
        $part = $path.'.part';

        return ($this->http ?? app('http'))->async()
            ->sink($part)
            ->timeout(0)
            ->connectTimeout(10)
            ->withOptions(['curl' => [CURLOPT_LOW_SPEED_LIMIT => self::LOW_SPEED_LIMIT, CURLOPT_LOW_SPEED_TIME => self::LOW_SPEED_TIME]])
            ->get($url)
            ->then(function (Response $response) use ($url, $path, $part): string {
                if (! $response->successful()) {
                    throw StargazerException::requestFailed($response->status(), $url, '');
                }

                rename($part, $path);

                return $path;
            })
            ->error(function (Throwable $e) use ($part): never {
                @unlink($part);

                throw $e;
            });
    }

    /**
     * A DTO's own fetch: the body arrives in memory, then lands on disk.
     * @param MediaSource $source
     * @param string $path
     * @return Promise
     */
    protected function hold(MediaSource $source, string $path): Promise
    {
        return ($source->fetch)()->then(function (Response $response) use ($source, $path): string {
            if (! $response->successful()) {
                throw StargazerException::requestFailed($response->status(), $source->url, '');
            }

            file_put_contents($path, $response->body(), LOCK_EX);

            return $path;
        });
    }
}
