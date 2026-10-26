<?php

use App\NutsAndBolts\Links;

it('tags the studio link for traffic-watch', function () {
    expect(Links::studio('welcome', 'made-with-adhd'))
        ->toBe('https://projectsaturnstudios.com/?utm_source=stargazer&utm_medium=app&utm_campaign=welcome&utm_content=made-with-adhd');
});

it('tags the Stargazer site link for traffic-watch', function () {
    expect(Links::site('welcome', 'website'))
        ->toBe('https://projectsaturnstudios.com/stargazer?utm_source=stargazer&utm_medium=app&utm_campaign=welcome&utm_content=website');
});

it('carries the year in the credit line', function () {
    expect(Links::credit(2026))->toBe('Made with ADHD by Project Saturn Studios © 2026');
});
