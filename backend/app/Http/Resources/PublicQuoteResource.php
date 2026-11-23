<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * A public quote shown to other readers: the quote with its likes and whether the viewer liked it.
 */
class PublicQuoteResource extends QuoteResource
{
    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            // Guests have no likes: the column is only selected for a signed-in viewer.
            'liked_by_me' => (bool) ($this->resource->getAttributes()['liked_by_me'] ?? false),
        ];
    }
}
