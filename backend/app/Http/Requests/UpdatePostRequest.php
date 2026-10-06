<?php

namespace App\Http\Requests;

use App\Enums\PostType;
use App\Models\Post;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePostRequest extends FormRequest
{
    /**
     * Checked before the body, so somebody else's post is "not found" whatever is sent to it.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('post'));
    }

    /**
     * A quote post has its comment to change, a review its rating and text. Anything else is ignored.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Post $post */
        $post = $this->route('post');

        return match ($post->type) {
            PostType::Quote => [
                'comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
            ],
            PostType::Review => [
                'rating' => ['sometimes', 'required', 'integer', 'between:1,5'],
                'content' => ['sometimes', 'nullable', 'string', 'max:5000'],
            ],
        };
    }
}
