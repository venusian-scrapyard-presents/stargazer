<?php

use App\NutsAndBolts\AppIcon;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/stargazer-icon-'.bin2hex(random_bytes(4));
    mkdir($this->dir.'/art', 0775, true);
    file_put_contents($this->dir.'/art/icon-128.png', 'png bytes');
});

afterEach(function () {
    foreach (['storage/icon-128.png', 'art/icon-128.png'] as $file) {
        if (is_file($this->dir.'/'.$file)) {
            unlink($this->dir.'/'.$file);
        }
    }
    foreach (['storage', 'art', ''] as $folder) {
        if (is_dir($this->dir.'/'.$folder)) {
            rmdir($this->dir.'/'.$folder);
        }
    }
});

it('copies the icon out to a plain file the toolkit can open', function () {
    $path = AppIcon::file($this->dir.'/art/icon-128.png', $this->dir.'/storage/icon-128.png');

    expect($path)->toBe($this->dir.'/storage/icon-128.png')
        ->and(file_get_contents($path))->toBe('png bytes');
});

it('leaves a copy that is already there', function () {
    mkdir($this->dir.'/storage');
    file_put_contents($this->dir.'/storage/icon-128.png', 'older copy');

    AppIcon::file($this->dir.'/art/icon-128.png', $this->dir.'/storage/icon-128.png');

    expect(file_get_contents($this->dir.'/storage/icon-128.png'))->toBe('older copy');
});

it('answers null when the source is gone', function () {
    expect(AppIcon::file($this->dir.'/art/missing.png', $this->dir.'/storage/icon-128.png'))->toBeNull();
});
