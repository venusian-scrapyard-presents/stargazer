<?php

use App\NutsAndBolts\FramePlayer;

it('steps one frame per step while playing and loops the day', function () {
    $player = new FramePlayer();
    $player->load(3, 10.0);

    expect($player->isPlaying())->toBeTrue()
        ->and($player->advance(10.0 + FramePlayer::STEP / 2))->toBeNull()
        ->and($player->advance(10.0 + FramePlayer::STEP))->toBe(1)
        ->and($player->advance(10.0 + FramePlayer::STEP * 2))->toBe(2)
        ->and($player->advance(10.0 + FramePlayer::STEP * 3))->toBe(0);
});

it('steps once after a long stall, not once per missed step', function () {
    $player = new FramePlayer();
    $player->load(13, 0.0);

    expect($player->advance(5.0))->toBe(1)
        ->and($player->advance(5.0 + FramePlayer::STEP / 2))->toBeNull();
});

it('does not play one frame or none', function (int $count) {
    $player = new FramePlayer();
    $player->load($count, 0.0);

    expect($player->isPlaying())->toBeFalse()
        ->and($player->canStep())->toBeFalse()
        ->and($player->advance(1.0))->toBeNull()
        ->and($player->next())->toBe(0)
        ->and($player->previous())->toBe(0);

    $player->toggle(0.0);
    expect($player->isPlaying())->toBeFalse();
})->with([0, 1]);

it('pauses and wraps on next and previous', function () {
    $player = new FramePlayer();
    $player->load(3, 0.0);

    expect($player->previous())->toBe(2)
        ->and($player->isPlaying())->toBeFalse()
        ->and($player->next())->toBe(0)
        ->and($player->advance(10.0))->toBeNull();
});

it('resumes from the shown frame, a full step later', function () {
    $player = new FramePlayer();
    $player->load(5, 0.0);
    $player->next();
    $player->next();

    $player->toggle(7.0);

    expect($player->isPlaying())->toBeTrue()
        ->and($player->advance(7.0 + FramePlayer::STEP / 2))->toBeNull()
        ->and($player->advance(7.0 + FramePlayer::STEP))->toBe(3);
});

it('does not advance once paused', function () {
    $player = new FramePlayer();
    $player->load(5, 0.0);
    $player->pause();

    expect($player->advance(10.0))->toBeNull()
        ->and($player->index())->toBe(0);
});

it('labels the frame shown', function () {
    $player = new FramePlayer();
    expect($player->label())->toBe('');

    $player->load(13, 0.0);
    $player->next();
    expect($player->label())->toBe('frame 2 of 13');
});

it('shows each frame for one second unless told otherwise', function () {
    $player = new FramePlayer();
    $player->load(3, 0.0);

    expect(FramePlayer::STEP)->toBe(1.0)
        ->and($player->advance(0.9))->toBeNull()
        ->and($player->advance(1.0))->toBe(1);

    $player->setStep(0.5);
    expect($player->step())->toBe(0.5)
        ->and($player->advance(1.4))->toBeNull()
        ->and($player->advance(1.5))->toBe(2);
});

it('offers the speeds in order, one second the default', function () {
    expect(array_keys(FramePlayer::SPEEDS))->toBe(['2 s per frame', '1 s per frame', '0.5 s per frame', '0.25 s per frame'])
        ->and(array_values(FramePlayer::SPEEDS))->toBe([2.0, 1.0, 0.5, 0.25])
        ->and(FramePlayer::defaultSpeed())->toBe(1);
});
