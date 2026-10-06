<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A reader who has blocked somebody is invisible to them: the public profile of the blocker is "not found" for the
 * blocked reader. The blocker still sees the profile of the blocked one, to be able to unblock.
 */
class UserPolicy
{
    public function viewProfile(User $viewer, User $profile): Response
    {
        $blockedByThem = $viewer->blockers()->whereKey($profile->id)->exists();

        return $blockedByThem ? Response::denyAsNotFound() : Response::allow();
    }
}
