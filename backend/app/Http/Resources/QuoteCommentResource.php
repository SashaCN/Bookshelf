<?php

namespace App\Http\Resources;

use App\Models\QuoteComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuoteComment
 */
class QuoteCommentResource extends JsonResource
{
    /**
     * Expects the user and quote relations loaded. An author with a private profile or without a handle
     * stays anonymous (no name, no handle), so a comment never exposes a reader who is not public.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerId = $request->user('sanctum')?->id;
        $known = $this->user->is_public && $this->user->username !== null;

        return [
            'id' => $this->id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'author' => [
                'username' => $known ? $this->user->username : null,
                'name' => $known ? $this->user->name : null,
            ],
            'can_delete' => $viewerId !== null && ($viewerId === $this->user_id || $viewerId === $this->quote->user_id),
        ];
    }
}
