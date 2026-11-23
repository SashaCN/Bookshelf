<?php

namespace App\Http\Controllers;

use App\Actions\Social\FindPublicProfile;
use App\Actions\Social\ProfileQuery;
use App\Actions\Social\ProfileSummary;
use App\Http\Resources\ProfileMemberResource;
use App\Http\Resources\PublicQuoteResource;
use App\Http\Resources\PublicReviewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class ProfileController extends Controller
{
    public function show(Request $request, string $username, FindPublicProfile $find, ProfileSummary $summary): JsonResponse
    {
        $profile = $find->handle($request->user('sanctum'), $username);

        return response()->json(['data' => $summary->handle($request->user('sanctum'), $profile)]);
    }

    public function quotes(Request $request, string $username, FindPublicProfile $find, ProfileQuery $query): AnonymousResourceCollection
    {
        $viewer = $request->user('sanctum');

        return PublicQuoteResource::collection($query->quotes($find->handle($viewer, $username), $viewer));
    }

    public function reviews(Request $request, string $username, FindPublicProfile $find, ProfileQuery $query): AnonymousResourceCollection
    {
        return PublicReviewResource::collection($query->reviews($find->handle($request->user('sanctum'), $username)));
    }

    public function followers(Request $request, string $username, FindPublicProfile $find, ProfileQuery $query): AnonymousResourceCollection
    {
        $viewer = $request->user('sanctum');
        $profile = $find->handle($viewer, $username);
        $followers = $query->followers($profile);

        // Only the owner learns which followers are new; the seen mark is theirs alone.
        if ($viewer?->is($profile)) {
            $seenAt = $profile->followers_seen_at;
            foreach ($followers->items() as $follower) {
                $follower->setAttribute(
                    'is_new',
                    $seenAt === null || Carbon::parse($follower->pivot->getAttribute('created_at'))->greaterThan($seenAt),
                );
            }
        }

        return ProfileMemberResource::collection($followers);
    }

    public function following(Request $request, string $username, FindPublicProfile $find, ProfileQuery $query): AnonymousResourceCollection
    {
        return ProfileMemberResource::collection($query->following($find->handle($request->user('sanctum'), $username)));
    }
}
