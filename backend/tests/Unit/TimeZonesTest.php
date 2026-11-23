<?php

use App\Support\TimeZones;

it('maps legacy names to the canonical one', function (string $legacy) {
    expect(TimeZones::normalize($legacy))->toBe('Europe/Kyiv');
})->with(['Europe/Kiev', 'Europe/Uzhgorod', 'Europe/Zaporozhye', ' Europe/Kiev ']);

it('keeps canonical names', function () {
    expect(TimeZones::normalize('Europe/Warsaw'))->toBe('Europe/Warsaw')
        ->and(TimeZones::normalize('Europe/Kyiv'))->toBe('Europe/Kyiv');
});

it('leaves unknown and non-string values for the validator', function () {
    expect(TimeZones::normalize('Mars/Olympus'))->toBe('Mars/Olympus')
        ->and(TimeZones::normalize(null))->toBeNull()
        ->and(TimeZones::normalize(''))->toBe('')
        ->and(TimeZones::normalize(['x']))->toBe(['x']);
});
