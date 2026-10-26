<?php

use App\NutsAndBolts\MediaCache;
use App\Workflows\EPIC\LoadEPICDay\EPICFrames;
use ProjectSaturnStudios\Stargazer\EPIC\Enums\EpicCollection;
use Voyager\Workflows\SharedBag;

it('keeps the collection each frame was downloaded from beside it', function () {
    $media = (new ReflectionClass(MediaCache::class))->newInstanceWithoutConstructor();
    $node = new EPICFrames($media, EpicCollection::NATURAL, fn () => null);
    $shared = new SharedBag();
    $shared->epic_images = ['first', 'second'];

    $node->postAsync($shared, $shared->epic_images, ['/cache/a.jpg', null]);

    expect($shared->epic_frames)->toBe([['/cache/a.jpg', 'first', EpicCollection::NATURAL]]);
});
