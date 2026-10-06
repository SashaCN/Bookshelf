<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCommentRequest extends FormRequest
{
    /**
     * Checked before the body: a post the reader may not see is "not found" whatever is sent to it.
     */
    public function authorize(): Response
    {
        return Gate::inspect('view', $this->route('post'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:1000'],
        ];
    }
}
