<?php

use App\NutsAndBolts\PictureMenu;

it('names a saved picture from its title and the cached file extension', function () {
    expect(PictureMenu::fileName('APOD 2026-10-09 M31: The Andromeda Galaxy', '/cache/ab12.JPG'))->toBe('APOD 2026-10-09 M31 The Andromeda Galaxy.jpg')
        ->and(PictureMenu::fileName('a/b\\c*d?"e<f>g|h', '/cache/x.png'))->toBe('a b c d e f g h.png')
        ->and(PictureMenu::fileName('  ...  ', '/cache/x.gif'))->toBe('picture.gif')
        ->and(PictureMenu::fileName('No extension', '/cache/blob'))->toBe('No extension');
});

it('keeps a long title to 120 characters before the extension', function () {
    expect(PictureMenu::fileName(str_repeat('é', 300), '/cache/x.jpg'))->toBe(str_repeat('é', 120).'.jpg');
});

it('picks a free path beside files already saved', function () {
    $dir = sys_get_temp_dir().'/picture-menu-'.bin2hex(random_bytes(4));
    mkdir($dir);

    try {
        expect(PictureMenu::freePath($dir, 'Moon.jpg'))->toBe("{$dir}/Moon.jpg");

        touch("{$dir}/Moon.jpg");
        touch("{$dir}/Moon (2).jpg");

        expect(PictureMenu::freePath($dir, 'Moon.jpg'))->toBe("{$dir}/Moon (3).jpg");
    } finally {
        array_map('unlink', glob("{$dir}/*"));
        rmdir($dir);
    }
});

it('saves to Downloads, or home when there is none', function () {
    $home = sys_get_temp_dir().'/picture-home-'.bin2hex(random_bytes(4));
    mkdir($home);

    try {
        expect(PictureMenu::downloadsDirectory($home))->toBe($home);

        mkdir("{$home}/Downloads");

        expect(PictureMenu::downloadsDirectory($home))->toBe("{$home}/Downloads");
    } finally {
        rmdir("{$home}/Downloads");
        rmdir($home);
    }
});

it('opens a window at the picture size, scaled down to fit, never tiny', function () {
    expect(PictureMenu::windowSize(800, 600))->toBe([800, 600])
        ->and(PictureMenu::windowSize(4000, 2000))->toBe([1100, 550])
        ->and(PictureMenu::windowSize(1000, 3000))->toBe([267, 800])
        ->and(PictureMenu::windowSize(100, 50))->toBe([240, 240])
        ->and(PictureMenu::windowSize(0, 0))->toBe(PictureMenu::WINDOW_MAX);
});

it('gives each picture its own Download and Open in Window items', function () {
    expect(PictureMenu::items(7))->toBe([
        ['id' => 'picture.7.save', 'label' => 'Download'],
        ['id' => 'picture.7.open', 'label' => 'Open in Window'],
    ])
        ->and(Surface\Windows\Menus\ContextMenu::parse(PictureMenu::items(7))->items())->toHaveKeys(['picture.7.save', 'picture.7.open']);
});

it('leaves a menu choice that is not a picture of its own alone', function () {
    $menu = new PictureMenu('test');

    expect($menu->activated(new Surface\Contracts\Windows\Mail\MenuActivated('stargazer', 'grid')))->toBeFalse()
        ->and($menu->activated(new Surface\Contracts\Windows\Mail\MenuActivated('stargazer', 'picture.999.save')))->toBeFalse();
});
