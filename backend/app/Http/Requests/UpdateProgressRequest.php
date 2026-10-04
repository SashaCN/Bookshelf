<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateProgressRequest extends FormRequest
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
            // The upper bound depends on the entry's page count and is checked by the action.
            'page' => ['required', 'integer', 'min:0', 'max:20000'],
        ];
    }
}
