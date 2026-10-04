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
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'content' => $this->content,
            'note' => $this->note,
            'page' => $this->page,
            'is_favorite' => $this->is_favorite,
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
}
