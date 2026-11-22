<?php

namespace App\Actions\Social;

use App\Models\User;
use App\Services\Stats\ReadingStatistics;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProfileSummary
{
    /**
     * @return array{username: string|null, name: string, books_finished_this_year: int, followers_count: int, following_count: int, is_following: bool, is_me: bool}
     */
    public function handle(?User $viewer, User $profile): array
    {
        $statistics = new ReadingStatistics($profile);
        $today = $statistics->today();

        return [
            'username' => $profile->username,
            'name' => $profile->name,
            'books_finished_this_year' => $statistics->booksFinished($today->startOfYear(), $today->endOfYear()),
            'followers_count' => $this->visible($profile->followers()),
            'following_count' => $this->visible($profile->following()),
            'is_following' => $viewer?->following()->whereKey($profile->id)->exists() ?? false,
            'is_me' => $viewer?->is($profile) ?? false,
        ];
    }

    /**
     * Only public readers are counted, as only they are listed.
     *
     * @param  BelongsToMany<User, User>  $relation
     */
    private function visible($relation): int
    {
        return $relation->where('users.is_public', true)->count();
    }
}
