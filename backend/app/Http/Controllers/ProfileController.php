<?php

namespace App\Http\Controllers;

use App\Actions\Social\FindPublicProfile;
use App\Actions\Social\ProfileQuery;
use App\Actions\Social\ProfileSummary;
use App\Http\Resources\ProfileMemberResource;
use App\Http\Resources\QuoteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfileController extends Controller
{
    public function show(Request $request, string $username, FindPublicProfile $find, ProfileSummary $summary): JsonResponse
    {
        $profile = $find->handle($request->user(), $username);

        return response()->json(['data' => $summary->handle($request->user(), $profile)]);
    }

    public function quotes(Request $request, string $username, FindPublicProfile $find, ProfileQuery $query): AnonymousResourceCollection
    {
        return QuoteResource::collection($query->quotes($find->handle($request->user(), $username)));
    }

    public function followers(Request $request, string $username, FindPublicProfile $find, ProfileQuery $query): AnonymousResourceCollection
    {
        return ProfileMemberResource::collection($query->followers($find->handle($request->user(), $username)));
    }

    public function following(Request $request, string $username, FindPublicProfile $find, ProfileQuery $query): AnonymousResourceCollection
    {
        return ProfileMemberResource::collection($query->following($find->handle($request->user(), $username)));
    }
}
