<?php

namespace App\Actions\Social;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FindPublicProfile
{
    /**
     * The reader behind a handle. A private or missing profile is "not found" for everybody
     * but its owner, so the API never confirms that a private reader exists.
     *
     * @throws ModelNotFoundException<User>
     */
    public function handle(User $viewer, string $username): User
    {
        return User::query()
            ->where('username', mb_strtolower($username))
            ->where(fn ($query) => $query->where('is_public', true)->orWhere('id', $viewer->id))
            ->firstOrFail();
    }
}
