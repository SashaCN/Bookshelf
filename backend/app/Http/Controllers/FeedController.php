<?php

namespace App\Http\Controllers;

use App\Actions\Social\ProfileQuery;
use App\Http\Resources\FeedQuoteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FeedController extends Controller
{
    /**
     * Public quotes of the readers the user follows, newest first.
     */
    public function __invoke(Request $request, ProfileQuery $query): AnonymousResourceCollection
    {
        return FeedQuoteResource::collection($query->feed($request->user()));
    }
}
