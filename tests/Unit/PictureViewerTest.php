<?php

use App\NutsAndBolts\PictureViewer;
use Surface\Contracts\HumanInput\MouseButton;

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

it('moves the picture with the left button only', function () {
    expect(PictureViewer::pans(MouseButton::LEFT))->toBeTrue()
        ->and(PictureViewer::pans(MouseButton::RIGHT))->toBeFalse()
        ->and(PictureViewer::pans(MouseButton::MIDDLE))->toBeFalse();
});

it('moves the centre against the pointer, in picture pixels', function () {
    // 10 points right on a 2× display at zoom 4: the centre moves 5 picture pixels left.
    expect(PictureViewer::panned(100.0, 10.0, 2.0, 4.0))->toBe(95.0)
        ->and(PictureViewer::panned(100.0, -10.0, 2.0, 4.0))->toBe(105.0);
});

it('zooms out on a notch down and in on a notch up', function () {
    expect(PictureViewer::wheelFactor(1.0, false))->toEqualWithDelta(0.8, 1e-12)
        ->and(PictureViewer::wheelFactor(-1.0, false))->toEqualWithDelta(1.25, 1e-12)
        ->and(PictureViewer::wheelFactor(-10.0, true))->toEqualWithDelta(1.01 ** 10, 1e-9);
});
