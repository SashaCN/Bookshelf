<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Handles are lower case, so "Olena_K" means "olena_k" instead of failing the format check.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('handle'))) {
            $this->merge(['handle' => mb_strtolower(trim($this->input('handle')))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'timezone' => ['sometimes', 'required', 'timezone:all_with_bc'],
            'handle' => [
                'sometimes',
                'required',
                'string',
                'regex:/^[a-z0-9_]{3,30}$/',
                Rule::unique('users', 'handle')->ignore($this->user()?->id),
            ],
            'bio' => ['sometimes', 'nullable', 'string', 'max:280'],
        ];
    }

    /**
     * The business rules answer with stable codes, which the client translates.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'handle.regex' => 'social.handle_invalid',
            'handle.unique' => 'social.handle_taken',
        ];
    }
}
