<?php

namespace App\Support;

use DateTimeZone;
use Throwable;

/**
 * Browsers still report some retired zone names (Europe/Kiev). Whether the host knows them depends on its tz
 * database (newer Debian/Ubuntu moved the legacy links out of the default package), so they are mapped to the
 * canonical name before validation and the answer is the same everywhere.
 */
final class TimeZones
{
    private const LEGACY = [
        'Europe/Kiev' => 'Europe/Kyiv',
        'Europe/Uzhgorod' => 'Europe/Kyiv',
        'Europe/Zaporozhye' => 'Europe/Kyiv',
    ];

    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $value = trim($value);

        if (isset(self::LEGACY[$value])) {
            return self::LEGACY[$value];
        }

        if (in_array($value, DateTimeZone::listIdentifiers(DateTimeZone::ALL), true)) {
            return $value;
        }

        try {
            return (new DateTimeZone($value))->getName();
        } catch (Throwable) {
            return $value;
        }
    }
}
