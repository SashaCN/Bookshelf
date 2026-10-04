<?php

namespace App\Http\Requests;

use App\Enums\BookStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adds a book to the library: either from Open Library (`openlibrary_work_key`) or by hand (`title`).
 */
class StoreUserBookRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'openlibrary_work_key' => ['required_without:title', 'string', 'regex:#^(/works/)?OL\d+W$#'],
            'title' => ['required_without:openlibrary_work_key', 'prohibits:openlibrary_work_key', 'string', 'max:255'],
            'authors' => ['nullable', 'array', 'max:5'],
            'authors.*' => ['string', 'max:255'],
            'published_year' => ['nullable', 'integer', 'min:1', 'max:'.((int) date('Y') + 1)],
            // The reader's own page count; for Open Library books it overrides the catalog value.
            'total_pages' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'status' => ['nullable', Rule::enum(BookStatus::class)],
            'started_at' => ['nullable', 'date', 'before_or_equal:now'],
            'finished_at' => ['nullable', 'date', 'before_or_equal:now', 'after_or_equal:started_at'],
        ];
    }
}
