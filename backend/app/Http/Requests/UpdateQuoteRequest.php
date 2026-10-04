<?php

namespace App\Http\Requests;

use App\Enums\QuoteType;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateQuoteRequest extends FormRequest
{
    /**
     * Checked before the body, so somebody else's quote is "not found" whatever is sent to it.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('quote'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', Rule::enum(QuoteType::class)],
            'content' => ['sometimes', 'required', 'string', 'max:2000'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:20000'],
            'is_favorite' => ['sometimes', 'boolean'],
        ];
    }
}
