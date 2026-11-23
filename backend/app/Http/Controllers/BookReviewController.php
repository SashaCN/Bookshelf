<?php

namespace App\Http\Controllers;

use App\Enums\PostType;
use App\Http\Resources\PostResource;
use App\Models\Book;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class BookReviewController extends Controller
{
    /**
     * What readers wrote about a book of the catalog, newest first, and its average rating. Hidden reviews and those
     * of blocked readers are neither listed nor counted. The reader's own review is named in the summary, because it may
     * be on a page that has not been asked for.
     */
    public function index(Request $request, Book $book): AnonymousResourceCollection
    {
        Gate::authorize('view', $book);

        $viewer = $request->user();

        $reviews = fn () => Post::query()
            ->where('posts.book_id', $book->id)
            ->where('posts.type', PostType::Review)
            ->visibleTo($viewer);

        $summary = $reviews()->selectRaw('count(*) as reviews_count, avg(posts.rating) as average_rating')->first();
        // Wherever it is in the list: the reader's own review may be on a page that has not been loaded.
        $myReviewId = $reviews()->where('posts.user_id', $viewer->id)->value('posts.id');

        return PostResource::collection($reviews()->withViewerState($viewer)->latest('posts.id')->simplePaginate(20))
            ->additional(['meta' => [
                'my_review_id' => $myReviewId === null ? null : (int) $myReviewId,
                'reviews_count' => (int) $summary?->getAttribute('reviews_count'),
                'average_rating' => $summary?->getAttribute('average_rating') === null ? null : round((float) $summary->getAttribute('average_rating'), 1),
            ]]);
    }
}
