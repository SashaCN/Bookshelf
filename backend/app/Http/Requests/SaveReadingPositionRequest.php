<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveReadingPositionRequest extends FormRequest
{
    /**
     * Checked before the body, so somebody else's text is "not found" whatever is sent to it.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('bookText'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Whether the text has this chapter is checked by the action.
            'chapter' => ['required', 'integer', 'min:0', 'max:100000'],
            'chapter_progress' => ['required', 'numeric', 'between:0,1'],
        ];
    }
}
