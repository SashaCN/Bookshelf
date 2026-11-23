<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchReadersRequest extends FormRequest
{
    /** What is searched for must be something: one letter would list half of the readers. */
    public const MIN_LENGTH = 2;

    /**
     * The "@" of "@handle" is not part of the handle, so it is left out before the length is checked: "@" and "@a" ask
     * for nothing.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => ltrim(trim($this->input('q')), '@')]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:'.self::MIN_LENGTH, 'max:50'],
        ];
    }

    /**
     * What to look for, without the "@" and with the characters that mean something to LIKE made plain.
     */
    public function term(): string
    {
        return addcslashes($this->validated('q'), '\\%_');
    }
}
