<?php

use App\NutsAndBolts\MediaVariant;
use ProjectSaturnStudios\Stargazer\ImageLibrary\Enums\ImageMediaType;

const VIDEO_ASSETS = [
    'http://images-assets.nasa.gov/video/A11/A11~orig.mp4',
    'http://images-assets.nasa.gov/video/A11/A11~mobile.mp4',
    'http://images-assets.nasa.gov/video/A11/A11~preview.mp4',
    'http://images-assets.nasa.gov/video/A11/A11.srt',
    'http://images-assets.nasa.gov/video/A11/metadata.json',
];

it('picks the mobile cut of a video over the original', function () {
    expect(MediaVariant::pick(VIDEO_ASSETS, ImageMediaType::VIDEO))->toBe('http://images-assets.nasa.gov/video/A11/A11~mobile.mp4');
});

it('falls back through the smaller video cuts before the original', function () {
    $without_mobile = array_values(array_filter(VIDEO_ASSETS, fn ($href) => ! str_contains($href, '~mobile')));

    expect(MediaVariant::pick($without_mobile, ImageMediaType::VIDEO))->toBe('http://images-assets.nasa.gov/video/A11/A11~preview.mp4')
        ->and(MediaVariant::pick(['x~orig.mp4', 'x~medium.mp4'], ImageMediaType::VIDEO))->toBe('x~medium.mp4')
        ->and(MediaVariant::pick(['x~orig.mp4'], ImageMediaType::VIDEO))->toBe('x~orig.mp4');
});

it('picks the medium image, then small, then the original, and ignores sidecars', function () {
    expect(MediaVariant::pick(['a~orig.jpg', 'a~medium.jpg', 'a~thumb.jpg', 'metadata.json'], ImageMediaType::IMAGE))->toBe('a~medium.jpg')
        ->and(MediaVariant::pick(['a~orig.png', 'a~small.png'], ImageMediaType::IMAGE))->toBe('a~small.png')
        ->and(MediaVariant::pick(['a~orig.jpg', 'captions.srt'], ImageMediaType::IMAGE))->toBe('a~orig.jpg');
});

it('picks a compressed audio file before the original', function () {
    expect(MediaVariant::pick(['s~orig.wav', 's~128k.mp3', 'metadata.json'], ImageMediaType::AUDIO))->toBe('s~128k.mp3');
});

it('has nothing to pick when no file fits the media type', function () {
    expect(MediaVariant::pick(['metadata.json', 'a.srt'], ImageMediaType::VIDEO))->toBeNull()
        ->and(MediaVariant::pick([], ImageMediaType::IMAGE))->toBeNull();
});

it('offers the bigger image cuts, biggest first, when a zoom wants more pixels', function () {
    $hrefs = ['a~orig.jpg', 'a~medium.jpg', 'a~small.jpg', 'a~thumb.jpg', 'a~large.jpg', 'metadata.json'];

    expect(MediaVariant::sharper($hrefs, 'a~medium.jpg'))->toBe(['a~orig.jpg', 'a~large.jpg'])
        ->and(MediaVariant::sharper($hrefs, 'a~thumb.jpg'))->toBe(['a~orig.jpg', 'a~large.jpg', 'a~medium.jpg', 'a~small.jpg'])
        ->and(MediaVariant::sharper($hrefs, 'a~orig.jpg'))->toBe([])
        ->and(MediaVariant::sharper(['a~medium.jpg', 'a~orig.jpg'], 'a~medium.jpg'))->toBe(['a~orig.jpg']);
});
