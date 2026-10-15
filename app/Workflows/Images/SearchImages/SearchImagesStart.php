<?php

namespace App\Workflows\Images\SearchImages;

use App\Enums\Outcome;
use App\GlobalVariables;
use App\Services\Images\ImagesService;
use App\Workflows\Fetch;
use App\Workflows\Report;
use App\Workflows\Validate;
use ProjectSaturnStudios\Stargazer\ImageLibrary\Enums\ImageMediaType;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

class SearchImagesStart extends Node
{
    /** Results asked for per search; the API pages at 100. */
    protected const int PAGE_SIZE = 60;

    public function __construct(
        protected ImagesService $images
    ){
        parent::__construct();
    }

    public function prep(SharedBag $shared): mixed
    {
        return [
            $shared,
            $this->images->query(),
            $this->images->mediaType(),
        ];
    }

    public function exec(mixed $prepRes): mixed
    {
        /**
         * @var GlobalVariables $bag
         * @var string $query
         * @var ImageMediaType $type
         */
        [$bag, $query, $type] = $prepRes;
        $nasa = $bag->nasa;
        $images = $this->images;

        $validate = new Validate(fn (): ?string => $query === '' ? 'Type something to search for.' : null);
        $validate->on('invalid')->next(new Report(Outcome::INVALID));

        // images-api.nasa.gov takes no API key.
        $fetch = new Fetch(fn () => $nasa->imageLibrary()->search($query)->media_type($type->value)->page_size(self::PAGE_SIZE), 'page');
        $images->failures($fetch);

        $validate->next($fetch)->next($images->render(function (GlobalVariables $bag) use ($images, $query, $type): ?string {
            $images->showResults($bag, $query, $type);

            return null;
        }));

        // A search worth sending is the one the page opens on next time.
        if ($query !== '') {
            $images->rememberSearch($query, $type);
        }

        $bag->flow->start(
            new AsyncFlow($validate),
            $images,
            "Searching for “{$query}”…"
        );

        return null;
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return null;
    }
}
