<?php

namespace App\Http\Controllers;

use App\Actions\Social\PostComments;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    /**
     * The comments of a post, newest first: a flat list. Hidden ones and those of blocked readers are not in it.
     */
    public function index(Request $request, Post $post): AnonymousResourceCollection
    {
        Gate::authorize('view', $post);

        $comments = $post->comments()
            ->visibleTo($request->user())
            ->with(['user', 'post'])
            ->latest('comments.id')
            ->simplePaginate(20);

        return CommentResource::collection($comments);
    }

    public function store(StoreCommentRequest $request, Post $post, PostComments $postComments): JsonResponse
    {
        $comment = $postComments->add($post, $request->user(), $request->validated('content'));

        return (new CommentResource($comment->load('post')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Delete a comment: its author and the author of the post may.
     */
    public function destroy(Comment $comment, PostComments $postComments): Response
    {
        Gate::authorize('delete', $comment);

        $postComments->remove($comment);

        return response()->noContent();
    }
}
