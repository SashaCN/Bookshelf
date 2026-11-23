<?php

namespace App\Services\OpenLibrary;

/**
 * ISBN validation and normalization to ISBN-13.
 */
final class Isbn
{
    /**
     * The ISBN-13 for a valid ISBN-10 or ISBN-13 (hyphens and spaces allowed), or null for anything else.
     */
    public static function normalize(string $input): ?string
    {
        $clean = strtoupper(str_replace(['-', ' '], '', trim($input)));

        if (preg_match('/^\d{9}[\dX]$/', $clean) === 1) {
            return self::isValidIsbn10($clean) ? self::isbn10To13($clean) : null;
        }

        if (preg_match('/^97[89]\d{10}$/', $clean) === 1) {
            return self::isValidIsbn13($clean) ? $clean : null;
        }

        return null;
    }

    private static function isValidIsbn10(string $isbn): bool
    {
        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $value = $isbn[$i] === 'X' ? 10 : (int) $isbn[$i];
            $sum += $value * (10 - $i);
        }

        return $sum % 11 === 0;
    }

    private static function isValidIsbn13(string $isbn): bool
    {
        return self::checkDigit13(substr($isbn, 0, 12)) === (int) $isbn[12];
    }

    private static function isbn10To13(string $isbn10): string
    {
        $body = '978'.substr($isbn10, 0, 9);

        return $body.self::checkDigit13($body);
    }

    private static function checkDigit13(string $twelve): int
    {
        $sum = 0;

        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $twelve[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10;
    }
}
