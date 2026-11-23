<?php

namespace App\Http\Resources;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerId = $request->user()?->id;

        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'content' => $this->content,
            'created_at' => $this->created_at?->toIso8601String(),
            'author' => [
                'handle' => $this->user->handle,
                'name' => $this->user->name,
            ],
            'is_mine' => $this->user_id === $viewerId,
            // The author of the comment and the author of the post may delete it.
            'can_delete' => $this->user_id === $viewerId || $this->post->user_id === $viewerId,
            // Only the author is ever shown a hidden comment.
            'is_hidden' => $this->isHidden(),
        ];
    }
}
