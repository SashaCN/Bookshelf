<?php

namespace App\Http\Controllers;

use App\Actions\Social\BlockReader;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\ReaderResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BlockController extends Controller
{
    /**
     * The readers the current reader has blocked, the latest blocked first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $blocked = $request->user()->blockedReaders()
            ->orderByPivot('created_at', 'desc')
            ->orderBy('users.id', 'desc')
            ->simplePaginate(20);

        return ReaderResource::collection($blocked);
    }

    /**
     * Block a reader: they do not see each other's content any more. Blocking again changes nothing.
     */
    public function store(Request $request, User $user, BlockReader $blocks): ProfileResource
    {
        Gate::authorize('viewProfile', $user);

        $blocks->block($request->user(), $user);

        return new ProfileResource(ProfileController::profileOf($user, $request->user()));
    }

    /**
     * Unblock a reader. Doing it for somebody who is not blocked changes nothing. A reader must be able to lift
     * their block even if that reader has blocked them in return, so there is no check of the other side here.
     */
    public function destroy(Request $request, User $user, BlockReader $blocks): Response
    {
        $blocks->unblock($request->user(), $user);

        return response()->noContent();
    }
}
