<?php

namespace App\Http\Controllers;

use App\Actions\Social\DeletePost;
use App\Actions\Social\PublishPost;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    /**
     * Publish one of the reader's quotes (a snapshot of it) or a review of a book from their library.
     */
    public function store(StorePostRequest $request, PublishPost $publish): JsonResponse
    {
        $post = $publish->handle($request->user(), $request->validated());

        return (new PostResource(self::forViewer($post, $request->user())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Post $post): PostResource
    {
        Gate::authorize('view', $post);

        return new PostResource(self::forViewer($post, $request->user()));
    }

    /**
     * Change the comment of a quote post, or the rating and the text of a review. Only the author may.
     */
    public function update(UpdatePostRequest $request, Post $post): PostResource
    {
        $post->update($request->validated());

        return new PostResource(self::forViewer($post, $request->user()));
    }

    /**
     * Delete a post. The quote it was made from stays.
     */
    public function destroy(Post $post, DeletePost $deletePost): Response
    {
        Gate::authorize('delete', $post);

        $deletePost->handle($post);

        return response()->noContent();
    }

    /**
     * The post with everything the resource needs to show it to the viewer.
     */
    public static function forViewer(Post $post, User $viewer): Post
    {
        return Post::query()->withViewerState($viewer)->findOrFail($post->id);
    }
}
