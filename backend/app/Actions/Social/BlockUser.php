<?php

namespace App\Actions\Social;

use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BlockUser
{
    public const PER_PAGE = 20;

    /**
     * Blocks the reader: both are hidden from each other from now on, so the follows between them
     * and the likes on each other's quotes go away, all in one transaction. Blocking twice changes nothing.
     *
     * @throws ValidationException
     */
    public function block(User $blocker, User $target): void
    {
        if ($blocker->is($target)) {
            throw ValidationException::withMessages(['username' => ['block.self']]);
        }

        DB::transaction(function () use ($blocker, $target): void {
            DB::table('user_blocks')->insertOrIgnore([
                'blocker_id' => $blocker->id,
                'blocked_id' => $target->id,
                'created_at' => now(),
            ]);

            DB::table('follows')
                ->where(fn ($query) => $query->where('follower_id', $blocker->id)->where('followed_id', $target->id))
                ->orWhere(fn ($query) => $query->where('follower_id', $target->id)->where('followed_id', $blocker->id))
                ->delete();

            $this->deleteLikes($blocker->id, $target->id);
            $this->deleteLikes($target->id, $blocker->id);
        });
    }

    /**
     * Lifts the block only; the follows and likes it removed do not come back.
     */
    public function unblock(User $blocker, User $target): void
    {
        DB::table('user_blocks')
            ->where('blocker_id', $blocker->id)
            ->where('blocked_id', $target->id)
            ->delete();
    }

    /**
     * The reader behind a handle, whatever their visibility: blocking a private reader is allowed.
     * Only a missing handle is "not found".
     *
     * @throws ModelNotFoundException<User>
     */
    public function find(string $username): User
    {
        return User::query()->where('username', mb_strtolower($username))->firstOrFail();
    }

    /**
     * @return Paginator<int, User>
     */
    public function blocked(User $blocker): Paginator
    {
        return User::query()
            ->join('user_blocks', 'user_blocks.blocked_id', '=', 'users.id')
            ->where('user_blocks.blocker_id', $blocker->id)
            ->select('users.*')
            ->orderByDesc('user_blocks.created_at')
            ->orderByDesc('users.id')
            ->simplePaginate(self::PER_PAGE);
    }

    /**
     * Removes the likes the user put on the quotes of the owner.
     */
    private function deleteLikes(int $userId, int $ownerId): void
    {
        DB::table('quote_likes')
            ->where('user_id', $userId)
            ->whereIn('quote_id', DB::table('quotes')->where('user_id', $ownerId)->select('id'))
            ->delete();
    }
}
