<?php

namespace App\Models;

use ProjectSaturnStudios\Stargazer\APOD\DataObjects\AstronomyPicture;
use Voyager\Database\Instrument\Model;

/**
 * One APOD day as the API gave it, so the day loads without asking again.
 */
class ApodDay extends Model
{
    /** AstronomyPicture's fields, which are this table's columns. */
    public const array FIELDS = [
        'date', 'title', 'explanation', 'url', 'hdurl', 'media_type', 'copyright', 'thumbnail_url', 'permalink', 'alt',
    ];

    protected $fillable = self::FIELDS;

    public function toPicture(): AstronomyPicture
    {
        return new AstronomyPicture(...array_intersect_key($this->getAttributes(), array_flip(self::FIELDS)));
    }
}
