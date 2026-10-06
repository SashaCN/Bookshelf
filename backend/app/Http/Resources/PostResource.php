<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A post as the current reader sees it. Of the author only the name and the handle are given, never the e-mail
 * or the time zone. Expects what Post::withViewerState() loads.
 *
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'content' => $this->content,
            // The author's own thought about a quoted passage.
            'comment' => $this->comment,
            'rating' => $this->rating,
            'page' => $this->page,
            'likes_count' => $this->likes_count,
            // What the reader sees: comments hidden by moderation and those of blocked readers are not counted.
            'comments_count' => $this->visible_comments_count ?? $this->comments_count,
            'liked_by_me' => (bool) ($this->liked_by_me ?? false),
            'is_mine' => $this->user_id === $request->user()?->id,
            // Only the author is ever shown a hidden post.
            'is_hidden' => $this->isHidden(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'author' => [
                'handle' => $this->user->handle,
                'name' => $this->user->name,
            ],
            'book' => [
                'id' => $this->book->id,
                'title' => $this->book->title,
                'authors' => $this->book->authors->pluck('name')->all(),
                'cover_url' => $this->book->cover_url,
            ],
        ];
    }
}
