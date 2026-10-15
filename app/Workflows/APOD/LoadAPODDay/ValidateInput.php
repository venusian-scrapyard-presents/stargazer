<?php

namespace App\Workflows\APOD\LoadAPODDay;

use App\Enums\Outcome;
use App\GlobalVariables;
use App\NutsAndBolts\MediaSource;
use App\Services\APOD\APODService;
use App\Workflows\Fetch;
use App\Workflows\APOD\ArchiveAPODDays;
use App\Workflows\MediaDownload;
use App\Workflows\Render;
use App\Workflows\Report;
use Closure;
use ProjectSaturnStudios\Stargazer\APOD\DataObjects\AstronomyPicture;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

class ValidateInput extends Node
{
    /** (GlobalVariables $bag): void — the failure painter, set once the day is known. */
    protected ?Closure $paint = null;

    public function __construct(
        protected Closure $check,
        protected APODService $service
    )
    {
        parent::__construct();
    }

    /**
     * Read workflow state and prepare input for execAsync().
     */
    public function prep(SharedBag $shared): mixed
    {
        /** @var GlobalVariables $shared */
        $warn = $shared->warn;
        $warn("Now inside ValidateInput");

        return $shared;
    }

    /**
     * Perform the node's work through the configured async runtime.
     */
    public function exec(mixed $prepRes): mixed
    {
        return ($this->check)($prepRes);
    }

    /**
     * Write results into shared state and return the next action.
     */
    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        /** @var GlobalVariables $shared */

        if (is_null($execRes))
        {
            $results = $this->setAuto($shared);
        }
        else
        {
            $this->next(new Report(Outcome::INVALID), 'invalid');
            $shared->error = $execRes;

            $results = 'invalid';
        }

        return $results;
    }

    private function setAuto(GlobalVariables $shared): string
    {
        $nasa = $shared->nasa;
        $date = $this->service->date()->date()?->format('Y-m-d');
        // Every failure route ends by showing the failure in place of the media.
        $this->paint = fn (GlobalVariables $bag) => $this->service->showFailure($bag, $date);
        // The day comes from apod_days when it is stored; fetched, it is stored for next time.
        $recall = new RecallAPODDay($date);
        $this->next($recall, 'recall');
        $fetch = new Fetch(fn () => $nasa->apod()->date($date), 'picture');
        $this->service->failures($fetch, $this->paint);
        $recall->on('fetch')->next($fetch);

        // Route on what the day holds: a picture, a playable video, or an embed (YouTube, Vimeo).
        $route = $this->service->render(function (GlobalVariables $bag): string {
            /** @var AstronomyPicture $picture */
            $picture = $bag->picture;
            $this->service->caption($picture);

            return $picture->mediaKind() ?? 'embed';
        });
        $recall->on('stored')->next($route);
        $fetch->next(new ArchiveAPODDays('picture'))->next($route);

        $this->pictureDownload($route);
        $this->videoDownload($route);
        $this->thumbnailDownload($route);

        return 'recall';
    }

    private function pictureDownload(Render $route): void
    {
        $hd = $this->service->hd()->isChecked();

        $picture_download = new MediaDownload($this->service->media, fn (GlobalVariables $bag) => new MediaSource(
            $hd && ! is_null($bag->picture->hdurl) ? $bag->picture->hdurl : $bag->picture->url,
            fn () => $bag->picture->render($hd),
        ), 'file');

        $service = $this->service;
        $this->service->failures($picture_download, $this->paint);
        $route->on('picture')
            ->next($picture_download)
            ->next($this->service->render(function (GlobalVariables $bag) use($service): ?string {
                $service->showPicture($bag->file);
                $service->done($bag, "{$bag->picture->date}: {$bag->picture->title}");

                return null;
            }));
    }

    private function videoDownload(Render $route): void
    {
        $video_download = new MediaDownload($this->service->media, fn (GlobalVariables $bag) => is_null($this->service->video()) ? null : MediaSource::of($bag->picture->url), 'file');
        $this->service->failures($video_download, $this->paint);
        $service = $this->service;
        $route->on('video')->next($video_download)
            ->next($this->service->render(function (GlobalVariables $bag) use($service): ?string {
                $service->showVideo($bag->file, $bag->picture->url);
                $service->done($bag, "{$bag->picture->date}: {$bag->picture->title} (video)");

                return null;
            }));
    }

    private function thumbnailDownload(Render $route): void
    {
        $thumbnail = new MediaDownload($this->service->media, fn (GlobalVariables $bag) => is_null($bag->picture->thumbnail_url)
            ? null
            : MediaSource::of($bag->picture->thumbnail_url), 'file');
        $this->service->failures($thumbnail, $this->paint);
        $service = $this->service;
        $route->on('embed')->next($thumbnail)
            ->next($this->service->render(function (GlobalVariables $bag) use($service): ?string {
                $service->showLink($bag->picture->permalink ?? $bag->picture->url, $bag->file ?? null);
                $service->done($bag, "{$bag->picture->date}: {$bag->picture->title} (embedded video — link below)");

                return null;
            }));
    }
}
