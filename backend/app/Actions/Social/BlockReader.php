<?php

namespace App\Actions\Social;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BlockReader
{
    /**
     * Idempotent. Neither reader sees the other's content any more (see VisibleToReaders), and the follows between
     * them in both directions are cut, so unblocking later does not bring a stranger's posts back into a feed.
     *
     * @throws ValidationException
     */
    public function block(User $blocker, User $target): void
    {
        if ($blocker->is($target)) {
            throw ValidationException::withMessages(['handle' => ['social.cannot_block_self']]);
        }

        DB::transaction(function () use ($blocker, $target): void {
            DB::table('blocks')->insertOrIgnore(['user_id' => $blocker->id, 'blocked_id' => $target->id]);

            DB::table('follows')
                ->where(fn ($query) => $query->where('follower_id', $blocker->id)->where('followed_id', $target->id))
                ->orWhere(fn ($query) => $query->where('follower_id', $target->id)->where('followed_id', $blocker->id))
                ->delete();
        });
    }

    public function unblock(User $blocker, User $target): void
    {
        DB::table('blocks')->where('user_id', $blocker->id)->where('blocked_id', $target->id)->delete();
    }
}
