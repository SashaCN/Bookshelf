<?php

namespace App\Http\Controllers;

use App\Actions\Social\LikePost;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LikeController extends Controller
{
    /**
     * Like a post. Liking again changes nothing. Answers with the post, so the counter on screen is the exact one.
     */
    public function store(Request $request, Post $post, LikePost $likes): PostResource
    {
        Gate::authorize('view', $post);

        $likes->like($post, $request->user());

        return new PostResource(PostController::forViewer($post, $request->user()));
    }

    /**
     * Take the like back. Doing it without a like changes nothing. A reader may always take their own like away, even
     * from a post they can no longer see (its author blocked them, or moderation hid it); such a post is not shown
     * to them again, the answer has no data.
     */
    public function destroy(Request $request, Post $post, LikePost $likes): PostResource|JsonResponse
    {
        $likes->unlike($post, $request->user());

        if (Gate::forUser($request->user())->denies('view', $post)) {
            return response()->json(['data' => null]);
        }

        return new PostResource(PostController::forViewer($post, $request->user()));
    }
}
