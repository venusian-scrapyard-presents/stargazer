<?php

use Symfony\Component\Process\Process;

it('boots through rocket and lists the stargazer sketch', function () {
    $process = new Process([PHP_BINARY, 'rocket', 'list'], dirname(__DIR__, 2));

    $process->run();

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('stargazer');
});
