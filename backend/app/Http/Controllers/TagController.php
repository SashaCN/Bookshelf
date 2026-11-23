<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * The reader's tags with the number of their quotes each, ordered by name. Tags no quote carries are not listed.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $tags = Tag::query()
            ->whereBelongsTo($request->user())
            ->withCount('quotes')
            ->having('quotes_count', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag) => ['name' => $tag->name, 'quotes_count' => (int) $tag->quotes_count]);

        return response()->json(['data' => $tags]);
    }
}
