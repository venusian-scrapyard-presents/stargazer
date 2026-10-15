<?php

namespace App\Models;

use ProjectSaturnStudios\Stargazer\ImageLibrary\DataObjects\ImageItemData;
use Voyager\Database\Instrument\Model;

/**
 * One Image Library item as the API gave it, with its asset manifest's files, so opening it
 * again asks for nothing.
 */
class ImageItem extends Model
{
    /** ImageItemData's fields in the API's spelling, which are this table's columns. */
    public const array FIELDS = [
        'nasa_id', 'title', 'description', 'description_508', 'center', 'date_created', 'media_type',
        'keywords', 'album', 'photographer', 'secondary_creator', 'location',
    ];

    /** The columns that hold a list, stored as JSON. */
    public const array LISTS = ['keywords', 'album', 'hrefs'];

    protected $fillable = [...self::FIELDS, 'hrefs'];

    protected function casts(): array
    {
        return array_fill_keys(self::LISTS, 'array');
    }

    /**
     * The row for an item and its manifest files, ready for a query-builder insert (which
     * bypasses the casts, so the lists are encoded here).
     * @param ImageItemData $item
     * @param list<string> $hrefs
     * @return array<string, string|null>
     */
    public static function row(ImageItemData $item, array $hrefs): array
    {
        return [
            'nasa_id' => $item->nasaId,
            'title' => $item->title,
            'description' => $item->description,
            'description_508' => $item->description508,
            'center' => $item->center,
            'date_created' => $item->dateCreated,
            'media_type' => $item->mediaType?->value,
            'keywords' => json_encode($item->keywords->values()->all()),
            'album' => json_encode($item->album->values()->all()),
            'photographer' => $item->photographer,
            'secondary_creator' => $item->secondaryCreator,
            'location' => $item->location,
            'hrefs' => json_encode(array_values($hrefs)),
        ];
    }

    public function toItem(): ImageItemData
    {
        return ImageItemData::fromArray(array_intersect_key($this->attributesToArray(), array_flip(self::FIELDS)));
    }

    /**
     * The manifest files stored with the item.
     * @return list<string>
     */
    public function hrefs(): array
    {
        return array_values(array_filter((array) ($this->hrefs ?? []), 'is_string'));
    }
}
