<?php

use App\Runner\Sketches\Stargazer;
use Surface\Contracts\Windows\ToolkitWindow;

it('ticks screens only while running with the main window open', function () {
    $open = Mockery::mock(ToolkitWindow::class);
    $open->allows('isOpen')->andReturn(true);
    // A red-button close removes the window's primitives before its close mail reaches the loop.
    $closed = Mockery::mock(ToolkitWindow::class);
    $closed->allows('isOpen')->andReturn(false);

    expect(Stargazer::ticks('RUNNING', $open))->toBeTrue()
        ->and(Stargazer::ticks('RUNNING', $closed))->toBeFalse()
        ->and(Stargazer::ticks('RUNNING', null))->toBeFalse()
        ->and(Stargazer::ticks('ENDING', $open))->toBeFalse();
});
