<?php

declare(strict_types=1);

namespace Lab8;

/**
 * Hash functions for the name tables. Each one turns a string into a non-negative integer;
 * the table then takes `% bucketCount` to get the bucket.
 */
final class Hash
{
    /** The names of the functions that can be passed to a table. */
    public const NAMES = ['sum', 'poly31', 'pjw'];

    /** Sum of the character codes. Simple, but anagrams and short names collide a lot. */
    public static function sum(string $key): int
    {
        $h = 0;
        for ($i = 0, $n = strlen($key); $i < $n; $i++) {
            $h += ord($key[$i]);
        }
        return $h;
    }

    /** Polynomial: h = h * 31 + c (the hash of Java strings). The position of a character matters. */
    public static function poly31(string $key): int
    {
        $h = 0;
        for ($i = 0, $n = strlen($key); $i < $n; $i++) {
            $h = ($h * 31 + ord($key[$i])) & 0x7FFFFFFF;
        }
        return $h;
    }

    /** PJW / ELF hash: shifts the bits left by 4 and mixes the high 4 bits back in. Used in compilers. */
    public static function pjw(string $key): int
    {
        $h = 0;
        for ($i = 0, $n = strlen($key); $i < $n; $i++) {
            $h = (($h << 4) + ord($key[$i])) & 0xFFFFFFFF;
            $high = $h & 0xF0000000;
            if ($high !== 0) {
                $h ^= $high >> 24;
            }
            $h &= ~$high & 0xFFFFFFFF;
        }
        return $h;
    }

    /** Calls the function by name and returns the bucket number. */
    public static function bucket(string $fn, string $key, int $buckets): int
    {
        if (!in_array($fn, self::NAMES, true)) {
            throw new \InvalidArgumentException("Unknown hash function: $fn");
        }
        return self::$fn($key) % $buckets;
    }
}
