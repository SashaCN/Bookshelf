<?php

namespace App\Http\Controllers;

use App\Actions\Social\QuoteComments;
use App\Http\Requests\StoreQuoteCommentRequest;
use App\Http\Resources\QuoteCommentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Like likes, comments are not owner-only: the quote is resolved in the action (public quote of a public profile, no block).
 */
class QuoteCommentController extends Controller
{
    public function index(Request $request, int $quote, QuoteComments $comments): AnonymousResourceCollection
    {
        return QuoteCommentResource::collection($comments->list($request->user('sanctum'), $quote));
    }

    public function store(StoreQuoteCommentRequest $request, int $quote, QuoteComments $comments): JsonResponse
    {
        $comment = $comments->post($request->user(), $quote, $request->validated('body'));

        return (new QuoteCommentResource($comment))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, int $quote, int $comment, QuoteComments $comments): Response
    {
        $comments->delete($request->user(), $quote, $comment);

        return response()->noContent();
    }
}
