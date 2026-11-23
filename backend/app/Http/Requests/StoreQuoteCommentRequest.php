<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteCommentRequest extends FormRequest
{
    /**
     * The body arrives trimmed (the TrimStrings middleware), so a blank one is "required".
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:1000'],
        ];
    }
}
