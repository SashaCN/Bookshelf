<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A date the reader typed ("I finished it on 20 March"). A bare date is a day in the reader's own time zone,
 * not in the server's, so it is neither refused as "tomorrow" late in the evening nor stored on the wrong day.
 */
final class ReaderDate implements ValidationRule
{
    public function __construct(private readonly string $timezone) {}

    /** The moment to store: a bare date becomes noon of that day for the reader, but never later than now. */
    public static function parse(string $value, string $timezone): Carbon
    {
        $moment = Carbon::parse($value, $timezone);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            $moment = $moment->setTime(12, 0)->min(now());
        }

        return $moment->utc();
    }

    /**
     * @param  Closure(string, string|null=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // The format is checked by the "date" rule next to this one.
        if (! is_string($value) || strtotime($value) === false) {
            return;
        }

        if (Carbon::parse($value, $this->timezone)->isFuture()) {
            $fail('validation.before_or_equal')->translate(['date' => 'now']);
        }
    }
}
