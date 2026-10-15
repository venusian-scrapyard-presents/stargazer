<?php

use App\Models\ImageItem;
use ProjectSaturnStudios\Stargazer\ImageLibrary\DataObjects\ImageItemData;
use ProjectSaturnStudios\Stargazer\ImageLibrary\Enums\ImageMediaType;

it('round-trips an item and its manifest files through a row', function () {
    $item = ImageItemData::fromArray([
        'nasa_id' => 'A11', 'title' => 'Apollo 11', 'description' => 'Rollout', 'center' => 'KSC',
        'date_created' => '1969-05-20T00:00:00Z', 'media_type' => 'video', 'keywords' => ['Apollo', 'Saturn V'],
        'photographer' => 'NASA',
    ]);
    $hrefs = ['http://x/A11~orig.mp4', 'http://x/A11~mobile.mp4'];

    $row = ImageItem::row($item, $hrefs);
    // As a read from image_items hydrates it: raw column values, lists still JSON.
    $stored = (new ImageItem)->setRawAttributes($row);
    $back = $stored->toItem();

    expect($row['keywords'])->toBe('["Apollo","Saturn V"]')
        ->and($stored->hrefs())->toBe($hrefs)
        ->and($back->nasaId)->toBe('A11')
        ->and($back->mediaType)->toBe(ImageMediaType::VIDEO)
        ->and($back->keywords->all())->toBe(['Apollo', 'Saturn V'])
        ->and($back->album->all())->toBe([])
        ->and($back->dateCreated)->toBe('1969-05-20T00:00:00Z');
});
