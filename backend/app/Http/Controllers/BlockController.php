<?php

namespace App\Http\Controllers;

use App\Actions\Social\BlockUser;
use App\Http\Resources\ProfileMemberResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BlockController extends Controller
{
    public function index(Request $request, BlockUser $blockUser): AnonymousResourceCollection
    {
        return ProfileMemberResource::collection($blockUser->blocked($request->user()));
    }

    public function store(Request $request, string $username, BlockUser $blockUser): Response
    {
        $blockUser->block($request->user(), $blockUser->find($username));

        return response()->noContent();
    }

    public function destroy(Request $request, string $username, BlockUser $blockUser): Response
    {
        $blockUser->unblock($request->user(), $blockUser->find($username));

        return response()->noContent();
    }
}
