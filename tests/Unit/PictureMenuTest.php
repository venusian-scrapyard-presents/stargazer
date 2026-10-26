<?php

use App\NutsAndBolts\PictureMenu;
use Surface\Contracts\Windows\Mail\Services\FilesChosen;

it('names a saved picture from its title and the cached file extension', function () {
    expect(PictureMenu::fileName('APOD 2026-10-09 M31: The Andromeda Galaxy', '/cache/ab12.JPG'))->toBe('APOD 2026-10-09 M31 The Andromeda Galaxy.jpg')
        ->and(PictureMenu::fileName('a/b\\c*d?"e<f>g|h', '/cache/x.png'))->toBe('a b c d e f g h.png')
        ->and(PictureMenu::fileName('  ...  ', '/cache/x.gif'))->toBe('picture.gif')
        ->and(PictureMenu::fileName('No extension', '/cache/blob'))->toBe('No extension');
});

it('keeps a long title to 120 characters before the extension', function () {
    expect(PictureMenu::fileName(str_repeat('é', 300), '/cache/x.jpg'))->toBe(str_repeat('é', 120).'.jpg');
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

it('gives each picture Save As, Copy Link when it has one, and Open in Window', function () {
    expect(PictureMenu::items(7, true))->toBe([
        ['id' => 'picture.7.save', 'label' => 'Save As…'],
        ['id' => 'picture.7.copy', 'label' => 'Copy Link'],
        ['id' => 'picture.7.open', 'label' => 'Open in Window'],
    ])
        ->and(PictureMenu::items(7, false))->toBe([
            ['id' => 'picture.7.save', 'label' => 'Save As…'],
            ['id' => 'picture.7.open', 'label' => 'Open in Window'],
        ])
        ->and(Surface\Windows\Menus\ContextMenu::parse(PictureMenu::items(7, true))->items())->toHaveKeys(['picture.7.save', 'picture.7.copy', 'picture.7.open']);
});

it('leaves a menu choice that is not a picture of its own alone', function () {
    $menu = new PictureMenu('test');

    expect($menu->activated(new Surface\Contracts\Windows\Mail\MenuActivated('stargazer', 'grid')))->toBeFalse()
        ->and($menu->activated(new Surface\Contracts\Windows\Mail\MenuActivated('stargazer', 'picture.999.save')))->toBeFalse();
});

it('copies the cached picture to the chosen path, over a file the panel let it replace', function () {
    $dir = sys_get_temp_dir().'/picture-save-'.bin2hex(random_bytes(4));
    mkdir($dir);

    try {
        file_put_contents("{$dir}/cached.jpg", 'new');
        file_put_contents("{$dir}/Moon.jpg", 'old');

        expect(PictureMenu::saveTo("{$dir}/cached.jpg", "{$dir}/Moon.jpg"))->toBeTrue()
            ->and(file_get_contents("{$dir}/Moon.jpg"))->toBe('new');
    } finally {
        array_map('unlink', glob("{$dir}/*"));
        rmdir($dir);
    }
});

it('writes nothing when the cached picture is gone', function () {
    $dir = sys_get_temp_dir().'/picture-save-'.bin2hex(random_bytes(4));
    mkdir($dir);

    try {
        expect(PictureMenu::saveTo("{$dir}/gone.jpg", "{$dir}/Moon.jpg"))->toBeFalse()
            ->and(file_exists("{$dir}/Moon.jpg"))->toBeFalse();
    } finally {
        rmdir($dir);
    }
});

it('takes only the save answers it asked for, and a cancel does nothing', function () {
    PictureMenu::expect('req-1', '/cache/none.jpg', 'Moon', 'apod');

    expect(PictureMenu::answered(new FilesChosen('req-2', [])))->toBeFalse()
        ->and(PictureMenu::answered(new FilesChosen('req-1', [])))->toBeTrue()
        ->and(PictureMenu::answered(new FilesChosen('req-1', [])))->toBeFalse();
});

it('answers false, not an ErrorException, when the copy fails under the app error handler', function () {
    $dir = sys_get_temp_dir().'/picture-save-'.bin2hex(random_bytes(4));
    mkdir($dir);
    mkdir("{$dir}/locked");
    file_put_contents("{$dir}/cached.jpg", 'new');
    chmod("{$dir}/locked", 0555);
    // The app's kernel turns every warning into an ErrorException, as this handler does.
    set_error_handler(fn (int $level, string $message) => throw new ErrorException($message, 0, $level));

    try {
        expect(PictureMenu::saveTo("{$dir}/cached.jpg", "{$dir}/locked/Moon.jpg"))->toBeFalse()
            ->and(file_exists("{$dir}/locked/Moon.jpg"))->toBeFalse();
    } finally {
        restore_error_handler();
        chmod("{$dir}/locked", 0755);
        rmdir("{$dir}/locked");
        unlink("{$dir}/cached.jpg");
        rmdir($dir);
    }
});

it('keeps running when the failure alert cannot open', function () {
    App\GlobalVariables::make()->window = Mockery::mock(Surface\Contracts\Windows\ToolkitWindow::class);
    PictureMenu::expect('req-gone', '/cache/gone.jpg', 'Moon', 'apod');

    try {
        expect(PictureMenu::answered(new FilesChosen('req-gone', ['/nowhere/Moon.jpg'])))->toBeTrue();
    } finally {
        App\GlobalVariables::make()->window = null;
    }
});
