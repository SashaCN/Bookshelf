<?php

namespace App\Actions\Social;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class FollowUser
{
    /**
     * @throws ValidationException
     */
    public function follow(User $follower, User $target): void
    {
        if ($follower->is($target)) {
            throw ValidationException::withMessages(['username' => ['follow.self']]);
        }

        // Following twice changes nothing.
        $follower->following()->syncWithoutDetaching([$target->id => ['created_at' => now()]]);
    }

    public function unfollow(User $follower, User $target): void
    {
        $follower->following()->detach($target->id);
    }
}
