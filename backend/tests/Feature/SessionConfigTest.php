<?php

it('keeps a reader signed in for 30 days (43 200 minutes)', function () {
    expect(config('session.lifetime'))->toBe(43200);
});

it('defaults to 30 days when SESSION_LIFETIME is not set', function () {
    // The environment of the test container sets the variable, so the default is checked in the file itself.
    expect(file_get_contents(config_path('session.php')))
        ->toContain("env('SESSION_LIFETIME', 43200)");
});
