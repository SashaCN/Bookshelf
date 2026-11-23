<?php

namespace App\Actions\Social;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    /**
     * The reader to unfollow: anyone the follower currently follows (even if they went private since),
     * otherwise the usual public lookup, so a private or missing reader that is not followed stays "not found".
     *
     * @throws ModelNotFoundException<User>
     */
    public function findToUnfollow(User $follower, string $username, FindPublicProfile $find): User
    {
        return $follower->following()->where('users.username', mb_strtolower($username))->first()
            ?? $find->handle($follower, $username);
    }
}
