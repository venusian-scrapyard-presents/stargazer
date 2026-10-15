<?php

namespace App\Workflows\APOD\LoadAPODDay;

use App\GlobalVariables;
use App\Services\APOD\APODService;
use App\Workflows\APOD\LoadAPODGallery\LoadAPODGalleryStart;
use Voyager\Workflows\AsyncFlow;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

class LoadAPODDayStart extends Node
{
    /** APOD's first day. */
    protected const string FIRST_DAY = '1995-06-16';

    public function __construct(
        protected APODService $apod
    ){
        parent::__construct();
    }

    public function prep(SharedBag $shared): mixed
    {
        /** @var GlobalVariables $shared */
        $date = $this->apod->date()->date()?->format('Y-m-d');

        $validate_node = new ValidateInput(fn (): ?string => match (true) {
            is_null($date) => 'Pick a day.',
            $date < self::FIRST_DAY => 'APOD starts on June 16, 1995.',
            $date > today()->format('Y-m-d') => 'That day has not happened yet.',
            default => null,
        }, $this->apod);


        return [
            $validate_node,
            $shared,
            $date
        ];
    }

    public function exec(mixed $prepRes): mixed
    {
        /**
         * @var ValidateInput $job
         * @var GlobalVariables $bag
         */
        [$job, $bag, $date] = $prepRes;

        $bag->window->setMenuBar('apod');

        $bag->flow->start(
            new AsyncFlow($job),
            $this->apod,
            "Fetching the picture for {$date}…"
        );

        return null;
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        return null;
    }
}
