<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateReaderPositionRequest extends FormRequest
{
    /**
     * Checked before the body, so somebody else's book is "not found" whatever is sent to it.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('readerDocument'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $last = max($this->route('readerDocument')->chapters_count - 1, 0);

        return [
            'chapter' => ['required', 'integer', 'min:0', "max:{$last}"],
            'fraction' => ['required', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
