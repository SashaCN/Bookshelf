<?php

namespace App\Http\Controllers;

use App\Actions\Social\LikeQuote;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Likes are not owner-only like the rest of /quotes/{quote}, so the quote is resolved in the action
 * (public quote of a public profile) instead of being bound through QuotePolicy.
 */
class QuoteLikeController extends Controller
{
    public function store(Request $request, int $quote, LikeQuote $likeQuote): Response
    {
        $likeQuote->like($request->user(), $quote);

        return response()->noContent();
    }

    public function destroy(Request $request, int $quote, LikeQuote $likeQuote): Response
    {
        $likeQuote->unlike($request->user(), $quote);

        return response()->noContent();
    }
}
