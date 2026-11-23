<?php

namespace App\Http\Resources;

use App\Models\UserBook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A public review shown to other readers: the book, the rating and the text. Nothing else of the library entry.
 *
 * @mixin UserBook
 */
class PublicReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'book' => [
                'title' => $this->book->title,
                'authors' => $this->book->authors->pluck('name')->all(),
                'cover_url' => $this->book->cover_url,
            ],
            'rating' => $this->rating,
            'review' => $this->review,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
