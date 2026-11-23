<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateReaderDocumentRequest extends FormRequest
{
    /**
     * Checked before the body, so somebody else's book is "not found" whatever is sent to it.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('readerDocument'));
    }

    /**
     * A foreign library entry fails like a missing one, so its existence is not revealed.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_book_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('user_books', 'id')->where('user_id', $this->user()?->id),
            ],
        ];
    }
}
