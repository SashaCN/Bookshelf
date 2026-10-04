<?php

namespace App\Http\Requests;

use App\Enums\BookStatus;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateUserBookRequest extends FormRequest
{
    /**
     * Checked before the body, so a foreign entry is "not found" whatever is sent to it.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('userBook'));
    }

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
