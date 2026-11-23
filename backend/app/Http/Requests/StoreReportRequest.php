<?php

namespace App\Http\Requests;

use App\Enums\ReportReason;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The report about a post (route parameter "post") or about a comment (route parameter "comment").
 */
class StoreReportRequest extends FormRequest
{
    /**
     * Checked before the body: what the reader may not see is "not found" whatever is sent to it.
     */
    public function authorize(): Response
    {
        return Gate::inspect('view', $this->route('post') ?? $this->route('comment'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
