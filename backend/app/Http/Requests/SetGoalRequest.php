<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class SetGoalRequest extends FormRequest
{
    /** The first year a goal can be set for. */
    private const FIRST_YEAR = 2000;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'target_books' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * A goal looks forward: this year or the next one. Earlier years are history, and the far future is a typo.
     */
    protected function prepareForValidation(): void
    {
        $year = (int) $this->route('year');
        $thisYear = (int) now($this->user()->timezone)->format('Y');

        if ($year < self::FIRST_YEAR || $year > $thisYear + 1) {
            throw ValidationException::withMessages(['year' => ['goals.year_out_of_range']]);
        }
    }
}
