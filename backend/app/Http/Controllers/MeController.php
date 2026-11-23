<?php

namespace App\Http\Controllers;

use App\Actions\Social\UpdateProfile;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MeController extends Controller
{
    /**
     * Get the authenticated user.
     */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Update the authenticated user's profile (name and time zone).
     */
    public function update(UpdateProfileRequest $request, UpdateProfile $updateProfile): UserResource
    {
        return new UserResource($updateProfile->handle($request->user(), $request->validated()));
    }

    /**
     * Mark the followers list as seen, so the new-follower badge resets.
     */
    public function followersSeen(Request $request): Response
    {
        $request->user()->forceFill(['followers_seen_at' => now()])->save();

        return response()->noContent();
    }
}
