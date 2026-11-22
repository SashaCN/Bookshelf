<?php

namespace App\Http\Controllers;

use App\Actions\Social\SearchProfiles;
use App\Http\Requests\SearchUsersRequest;
use App\Http\Resources\ProfileSearchResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserSearchController extends Controller
{
    public function __invoke(SearchUsersRequest $request, SearchProfiles $search): AnonymousResourceCollection
    {
        return ProfileSearchResource::collection($search->handle($request->user(), $request->validated('q')));
    }
}
