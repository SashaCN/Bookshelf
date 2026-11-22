<?php

namespace App\Actions\Social;

use App\Models\User;
use Illuminate\Support\Facades\DB;
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

        // One atomic statement: following twice, even at the same moment, changes nothing.
        DB::table('follows')->insertOrIgnore([
            'follower_id' => $follower->id,
            'followed_id' => $target->id,
            'created_at' => now(),
        ]);
    }

    public function unfollow(User $follower, User $target): void
    {
        $follower->following()->detach($target->id);
    }
}
