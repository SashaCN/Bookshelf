<?php

namespace App\Http\Requests;

use App\Enums\ReaderError;
use App\Services\Epub\EpubLimits;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreBookTextRequest extends FormRequest
{
    /**
     * Checked before the body, so somebody else's library entry is "not found" whatever is sent to it.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('userBook'));
    }

    /**
     * Only the size is checked here, and only against the limit of the upload; whether the file is an EPUB is for the
     * importer to find out from its content, not from what the client says its name or type is.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.(new EpubLimits)->maxFileKilobytes()],
        ];
    }

    /**
     * The messages are the codes the client translates.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => ReaderError::NoFile->value,
            'file.file' => ReaderError::NoFile->value,
            // A file that PHP itself turned away (over its own upload limit) fails here, before any other rule is looked at.
            'file.uploaded' => $this->uploadFailure()->value,
            'file.max' => ReaderError::TooLarge->value,
        ];
    }

    private function uploadFailure(): ReaderError
    {
        $error = $this->file('file')?->getError();

        return in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? ReaderError::TooLarge : ReaderError::NoFile;
    }
}
