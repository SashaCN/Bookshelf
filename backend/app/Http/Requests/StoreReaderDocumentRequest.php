<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReaderDocumentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // The size is in KB. What is inside the file is checked by the parser.
            'file' => ['required', 'file', 'extensions:epub', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['file.max' => 'reader.too_large'];
    }
}
