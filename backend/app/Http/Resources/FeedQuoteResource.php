<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * A quote of the feed: the quote itself and the reader who shared it.
 */
class FeedQuoteResource extends PublicQuoteResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'author' => (new ProfileMemberResource($this->user))->toArray($request),
        ];
    }
}
