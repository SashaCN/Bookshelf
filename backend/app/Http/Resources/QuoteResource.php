<?php

namespace App\Http\Resources;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quote
 */
class QuoteResource extends JsonResource
{
    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'content' => $this->content,
            'page' => $this->page,
            // Private to the owner: a public quote shows its words, not the reader's notes or favorites.
            $this->mergeWhen($request->user('sanctum')?->id === $this->user_id, fn () => [
                'note' => $this->note,
                'is_favorite' => $this->is_favorite,
            ]),
            // Tags are private labels: only the owner gets them, and only where the relation was loaded.
            'tags' => $this->when($this->exposesTags($request) && $this->resource->relationLoaded('tags'), fn () => $this->tags->pluck('name')->all()),
            'visibility' => $this->visibility,
            // Only where the likes were counted (the lists), never a misleading 0 elsewhere.
            'likes_count' => $this->when(array_key_exists('likes_count', $this->resource->getAttributes()), fn () => (int) $this->likes_count),
            'comments_count' => $this->when(array_key_exists('comments_count', $this->resource->getAttributes()), fn () => (int) $this->comments_count),
            'created_at' => $this->created_at?->toIso8601String(),
            // The library entry the quote belongs to, and enough of its book to show where the quote comes from.
            'user_book_id' => $this->user_book_id,
            'book' => $this->whenLoaded('userBook', fn () => [
                'title' => $this->userBook->book->title,
                'authors' => $this->userBook->book->authors->pluck('name')->all(),
                'cover_url' => $this->userBook->book->cover_url,
            ]),
        ];
    }

    /**
     * Whether this resource may show the quote's tags. Shared quotes (profiles, feed) never do.
     */
    protected function exposesTags(Request $request): bool
    {
        return $request->user('sanctum')?->id === $this->user_id;
    }
}
