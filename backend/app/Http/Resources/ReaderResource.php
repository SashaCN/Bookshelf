<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A reader in a list (search results, blocked readers): no e-mail, no time zone, no numbers.
 *
 * @mixin User
 */
class ReaderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'handle' => $this->handle,
            'name' => $this->name,
            'bio' => $this->bio,
            'is_me' => $this->id === $request->user()?->id,
            'is_following' => (bool) ($this->is_following ?? false),
        ];
    }
}
