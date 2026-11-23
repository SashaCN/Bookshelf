<?php

namespace App\Http\Controllers;

use App\Actions\Social\FollowReader;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FollowController extends Controller
{
    /**
     * Follow a reader. Following again changes nothing.
     */
    public function store(Request $request, User $user, FollowReader $follows): ProfileResource
    {
        Gate::authorize('viewProfile', $user);

        $follows->follow($request->user(), $user);

        return new ProfileResource(ProfileController::profileOf($user, $request->user()));
    }

    /**
     * Unfollow. Unfollowing somebody who is not followed changes nothing.
     */
    public function destroy(Request $request, User $user, FollowReader $follows): ProfileResource
    {
        Gate::authorize('viewProfile', $user);

        $follows->unfollow($request->user(), $user);

        return new ProfileResource(ProfileController::profileOf($user, $request->user()));
    }
}
