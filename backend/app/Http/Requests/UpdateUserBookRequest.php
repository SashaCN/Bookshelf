<?php

namespace App\Http\Requests;

use App\Enums\BookStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserBookRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::enum(BookStatus::class)],
            'rating' => ['sometimes', 'nullable', 'integer', 'between:1,5'],
            'total_pages' => ['sometimes', 'required', 'integer', 'min:1', 'max:20000'],
            // Used only when a book moves from "want" to "finished" (it was read earlier).
            'started_at' => ['nullable', 'date', 'before_or_equal:now'],
            'finished_at' => ['nullable', 'date', 'before_or_equal:now', 'after_or_equal:started_at'],
        ];
    }
}
