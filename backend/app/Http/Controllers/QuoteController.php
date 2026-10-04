<?php

namespace App\Http\Controllers;

use App\Actions\Quotes\PickQuoteOfTheDay;
use App\Actions\Quotes\SaveQuote;
use App\Enums\QuoteType;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Models\UserBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class QuoteController extends Controller
{
    /**
     * The reader's quotes, newest first, optionally only favorites, one type, or one library entry.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'favorite' => ['nullable', 'boolean'],
            'book' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(QuoteType::class)],
        ]);

        $quotes = $request->user()->quotes()
            ->with('userBook.book.authors')
            ->when($request->boolean('favorite'), fn ($query) => $query->where('is_favorite', true))
            ->when($filters['book'] ?? null, fn ($query, int $book) => $query->where('user_book_id', $book))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->latest('id')
            ->simplePaginate(20);

        return QuoteResource::collection($quotes);
    }

    /**
     * The quote of the day: the same one all day, another one tomorrow. Empty when there is nothing to show.
     */
    public function daily(Request $request, PickQuoteOfTheDay $pickQuote): JsonResponse|QuoteResource
    {
        $request->validate(['favorite' => ['nullable', 'boolean']]);

        $quote = $pickQuote->handle($request->user(), $request->boolean('favorite'));

        return $quote ? new QuoteResource($quote) : response()->json(['data' => null]);
    }

    /**
     * Save a quote or an insight from a book in the library.
     */
    public function store(StoreQuoteRequest $request, UserBook $userBook, SaveQuote $saveQuote): JsonResponse
    {
        $quote = $saveQuote->create($userBook, $request->validated());

        return (new QuoteResource($quote))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Quote $quote): QuoteResource
    {
        Gate::authorize('view', $quote);

        return new QuoteResource($quote->load('userBook.book.authors'));
    }

    /**
     * Edit a quote, or mark it as a favorite.
     */
    public function update(UpdateQuoteRequest $request, Quote $quote, SaveQuote $saveQuote): QuoteResource
    {
        return new QuoteResource($saveQuote->update($quote, $request->validated()));
    }

    public function destroy(Quote $quote): Response
    {
        Gate::authorize('delete', $quote);

        $quote->delete();

        return response()->noContent();
    }
}
