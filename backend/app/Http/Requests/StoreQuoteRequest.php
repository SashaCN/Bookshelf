<?php

namespace App\Http\Requests;

use App\Enums\QuoteType;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreQuoteRequest extends FormRequest
{
    /**
     * Checked before the body, so somebody else's library entry is "not found" whatever is sent to it.
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
            'type' => ['sometimes', 'required', Rule::enum(QuoteType::class)],
            'content' => ['required', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
            // Whether it fits the book is checked against the reader's page count by the action.
            'page' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'is_favorite' => ['sometimes', 'boolean'],
        ];
    }
}
