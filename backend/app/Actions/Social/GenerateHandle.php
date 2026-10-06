<?php

namespace App\Actions\Social;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Makes the first handle of a reader out of their name: "Олена Шевченко" becomes "olena_shevchenko", and when it is
 * taken, "olena_shevchenko4821" (a random number). The reader may change it later in the profile.
 *
 * A taken handle costs one look-up per try, whatever the number of readers with the same name: the new handle is
 * guessed, never counted up from the first free one.
 */
class GenerateHandle
{
    /** Used when nothing usable is left of the name (an emoji, a single letter...). */
    public const FALLBACK = 'reader';

    public const MAX_LENGTH = 30;

    /** Room left for a number behind the name, so "name" + "12345678" still fits. */
    private const MAX_BASE_LENGTH = 22;

    /** How many random numbers of a length are tried before the numbers get longer. */
    private const TRIES_PER_LENGTH = 3;

    /** The numbers have this many digits, one length after the other. */
    private const NUMBER_LENGTHS = [4, 6, 8];

    public function handle(string $name): string
    {
        $base = $this->base($name);

        if (! $this->taken($base)) {
            return $base;
        }

        foreach (self::NUMBER_LENGTHS as $digits) {
            for ($try = 0; $try < self::TRIES_PER_LENGTH; $try++) {
                $candidate = $this->withNumber($base, $this->randomNumber($digits));

                if (! $this->taken($candidate)) {
                    return $candidate;
                }
            }
        }

        // Hopelessly unlucky: letters and digits are many more than numbers alone, and a clash is left to the unique index.
        return $this->withNumber($base, strtolower(Str::random(8)));
    }

    private function base(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9_]/', '', Str::slug($name, '_', 'uk')) ?? '';
        $slug = trim(substr($slug, 0, self::MAX_BASE_LENGTH), '_');

        return strlen($slug) >= 3 ? $slug : self::FALLBACK;
    }

    private function randomNumber(int $digits): string
    {
        return (string) random_int(10 ** ($digits - 1), 10 ** $digits - 1);
    }

    /** The name, shortened where it must be so that the whole stays within the limit. */
    private function withNumber(string $base, string $number): string
    {
        return rtrim(substr($base, 0, self::MAX_LENGTH - strlen($number)), '_').$number;
    }

    private function taken(string $handle): bool
    {
        return User::query()->where('handle', $handle)->exists();
    }
}
