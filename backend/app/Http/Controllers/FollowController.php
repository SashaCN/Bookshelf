<?php

namespace App\Http\Controllers;

use App\Actions\Social\FindPublicProfile;
use App\Actions\Social\FollowUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FollowController extends Controller
{
    public function store(Request $request, string $username, FindPublicProfile $find, FollowUser $followUser): Response
    {
        $followUser->follow($request->user(), $find->handle($request->user(), $username));

        return response()->noContent();
    }

    public function destroy(Request $request, string $username, FindPublicProfile $find, FollowUser $followUser): Response
    {
        $followUser->unfollow($request->user(), $find->handle($request->user(), $username));

        return response()->noContent();
    }
}
