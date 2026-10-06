<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchReadersRequest;
use App\Http\Resources\PostResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\ReaderResource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProfileController extends Controller
{
    /**
     * Find readers by the beginning of their handle or of a word in their name (at least two characters). Readers who
     * have a block with the current reader, in either direction, are not found.
     */
    public function index(SearchReadersRequest $request): AnonymousResourceCollection
    {
        $viewer = $request->user();
        $term = $request->term();

        $readers = User::query()
            ->notBlockedWith($viewer)
            ->where(fn ($query) => $query
                ->where('handle', 'like', "{$term}%")
                ->orWhere('name', 'like', "{$term}%")
                ->orWhere('name', 'like', "% {$term}%"))
            ->withExists(['followers as is_following' => fn ($follows) => $follows->where('follows.follower_id', $viewer->id)])
            ->orderBy('handle')
            ->simplePaginate(20);

        return ReaderResource::collection($readers);
    }

    /**
     * The public profile. A reader who has blocked the current reader is "not found".
     */
    public function show(Request $request, User $user): ProfileResource
    {
        Gate::authorize('viewProfile', $user);

        return new ProfileResource(self::profileOf($user, $request->user()));
    }

    /**
     * The posts of one reader, newest first. Hidden posts are not in the list, and nothing of a reader the current
     * reader has blocked.
     */
    public function posts(Request $request, User $user): AnonymousResourceCollection
    {
        Gate::authorize('viewProfile', $user);

        $viewer = $request->user();

        $posts = Post::query()
            ->where('posts.user_id', $user->id)
            ->visibleTo($viewer)
            ->withViewerState($viewer)
            ->latest('posts.id')
            ->simplePaginate(20);

        return PostResource::collection($posts);
    }

    /**
     * The reader with the numbers and relations of a profile page, as the viewer sees them.
     */
    public static function profileOf(User $user, User $viewer): User
    {
        return User::query()->withProfileState($viewer)->findOrFail($user->id);
    }
}
