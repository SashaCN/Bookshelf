<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Handles are case-insensitive, so they are compared and stored in lowercase.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => mb_strtolower(trim($this->input('username')))]);
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
            'username' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z0-9_]{3,30}$/', Rule::unique('users', 'username')->ignore($this->user()?->id)],
            'is_public' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'timezone' => ['sometimes', 'required', 'timezone:all_with_bc'],
        ];
    }
}
