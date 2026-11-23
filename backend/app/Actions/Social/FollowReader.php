<?php

namespace App\Actions\Social;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FollowReader
{
    /**
     * Idempotent. Blocks of the target against the follower are handled before this (the target is "not found").
     *
     * @throws ValidationException
     */
    public function follow(User $follower, User $target): void
    {
        if ($follower->is($target)) {
            throw ValidationException::withMessages(['handle' => ['social.cannot_follow_self']]);
        }

        if ($follower->blockedReaders()->whereKey($target->id)->exists()) {
            throw ValidationException::withMessages(['handle' => ['social.user_blocked']]);
        }

        DB::table('follows')->insertOrIgnore(['follower_id' => $follower->id, 'followed_id' => $target->id]);
    }

    public function unfollow(User $follower, User $target): void
    {
        DB::table('follows')->where('follower_id', $follower->id)->where('followed_id', $target->id)->delete();
    }
}
