<?php

use App\Services\APOD\APODService;

it('names the gallery count in pictures', function () {
    expect(APODService::countLabel(1))->toBe('1 picture')
        ->and(APODService::countLabel(6))->toBe('6 pictures')
        ->and(APODService::countLabel(12))->toBe('12 pictures');
});

it('reports a loaded gallery in the right number', function () {
    expect(APODService::galleryDone(1, '2011-02-03', '2011-02-03', 1))->toBe('1 picture, 2011-02-03 to 2011-02-03; 1 preview loaded.')
        ->and(APODService::galleryDone(6, '2026-10-18', '2026-10-23', 5))->toBe('6 pictures, 2026-10-18 to 2026-10-23; 5 previews loaded.');
});
