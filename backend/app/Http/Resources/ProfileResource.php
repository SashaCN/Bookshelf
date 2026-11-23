<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public profile of a reader. It never contains the e-mail address or the time zone, and the numbers are
 * what the current reader may see. Expects what User::withProfileState() loads.
 *
 * @mixin User
 */
class ProfileResource extends JsonResource
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
            'posts_count' => (int) $this->posts_count,
            'followers_count' => (int) $this->followers_count,
            'following_count' => (int) $this->following_count,
            'is_following' => (bool) ($this->is_following ?? false),
            'is_me' => $this->id === $request->user()?->id,
            // The current reader has blocked this one.
            'is_blocked' => (bool) ($this->is_blocked ?? false),
        ];
    }
}
