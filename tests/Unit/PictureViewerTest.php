<?php

use App\NutsAndBolts\PictureViewer;

it('reads php.ini sizes', function () {
    expect(PictureViewer::bytes('128M'))->toBe(128 * 1024 ** 2)
        ->and(PictureViewer::bytes('1G'))->toBe(1024 ** 3)
        ->and(PictureViewer::bytes('512K'))->toBe(512 * 1024)
        ->and(PictureViewer::bytes('2048'))->toBe(2048)
        ->and(PictureViewer::bytes('-1'))->toBe(-1);
});

it('decodes a picture only when its pixels fit in the memory left', function () {
    $mb = 1024 ** 2;

    // Webb's 8256 × 5243 original is 173 MB of RGBA: too much at 128M, fine at 1G.
    expect(PictureViewer::fits(8256, 5243, 128 * $mb, 20 * $mb))->toBeFalse()
        ->and(PictureViewer::fits(8256, 5243, 1024 * $mb, 60 * $mb))->toBeTrue()
        // Hubble's deep field, 1771 × 1819, is 13 MB: fine at 128M.
        ->and(PictureViewer::fits(1771, 1819, 128 * $mb, 40 * $mb))->toBeTrue()
        ->and(PictureViewer::fits(1771, 1819, -1, 40 * $mb))->toBeTrue()
        ->and(PictureViewer::fits(0, 100, 1024 * $mb, 0))->toBeFalse();
});
