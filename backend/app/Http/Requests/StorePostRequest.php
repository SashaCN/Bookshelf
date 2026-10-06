<?php

namespace App\Http\Requests;

use App\Enums\PostType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    /**
     * What is checked depends on the type of the post; the fields of the other type are ignored. The quote and the
     * library entry have to be the author's own: for anybody else's they simply "do not exist".
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        $byType = match (PostType::tryFrom((string) $this->input('type'))) {
            PostType::Quote => [
                'quote_id' => ['required', 'integer', Rule::exists('quotes', 'id')->where('user_id', $userId)],
                'comment' => ['nullable', 'string', 'max:2000'],
            ],
            PostType::Review => [
                'user_book_id' => ['required', 'integer', Rule::exists('user_books', 'id')->where('user_id', $userId)],
                'rating' => ['required', 'integer', 'between:1,5'],
                'content' => ['nullable', 'string', 'max:5000'],
            ],
            null => [],
        };

        return ['type' => ['required', Rule::enum(PostType::class)]] + $byType;
    }
}
