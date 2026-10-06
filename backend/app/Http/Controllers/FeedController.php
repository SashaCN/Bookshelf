<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeedController extends Controller
{
    /**
     * The feed, newest first: the posts of the readers the current reader follows (and their own), or all of them.
     * Hidden posts and the posts of blocked readers are never in it.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate(['scope' => ['nullable', Rule::in(['following', 'all'])]]);

        $viewer = $request->user();
        $followingOnly = ($validated['scope'] ?? 'following') === 'following';

        $posts = Post::query()
            ->visibleTo($viewer)
            ->when($followingOnly, fn ($query) => $query->where(fn ($mine) => $mine
                ->where('posts.user_id', $viewer->id)
                ->orWhereIn('posts.user_id', DB::table('follows')->select('followed_id')->where('follower_id', $viewer->id))))
            ->withViewerState($viewer)
            ->latest('posts.id')
            ->simplePaginate(20);

        return PostResource::collection($posts);
    }
}
