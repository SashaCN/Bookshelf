<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportLibraryRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // The size is in KB. What is inside the file is checked by the reader.
        return ['file' => ['required', 'file', 'extensions:csv', 'max:2048']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_fill_keys(['file.required', 'file.file', 'file.extensions', 'file.max', 'file.uploaded'], 'library.import_invalid');
    }
}
