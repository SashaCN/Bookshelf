<?php

namespace App\Http\Controllers;

use App\Actions\Quotes\ExportQuotesMarkdown;
use App\Actions\Quotes\PickQuoteOfTheDay;
use App\Actions\Quotes\SaveQuote;
use App\Enums\QuoteType;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Models\Tag;
use App\Models\UserBook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuoteController extends Controller
{
    /**
     * The reader's quotes, newest first (best match first when searching), optionally only favorites, one type, one library entry, one tag, or a text.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $quotes = $this->filteredQuotes($request)
            ->withCount('likeRows as likes_count')
            ->with('userBook.book.authors', 'tags')
            ->latest('id')
            ->simplePaginate(20);

        return QuoteResource::collection($quotes);
    }

    /**
     * The same quotes as the list (same filters), as a Markdown file for download.
     */
    public function export(Request $request, ExportQuotesMarkdown $export): StreamedResponse
    {
        $user = $request->user();
        $quotes = $this->filteredQuotes($request);
        $filename = 'bookshelf-quotes-'.now($user->timezone)->toDateString().'.md';

        return response()->streamDownload(function () use ($export, $quotes) {
            foreach ($export->handle($quotes) as $piece) {
                echo $piece;
            }
        }, $filename, ['Content-Type' => 'text/markdown; charset=UTF-8']);
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

        return new QuoteResource($quote->load('userBook.book.authors', 'tags'));
    }

    /**
     * Edit a quote, or mark it as a favorite.
     */
    public function update(UpdateQuoteRequest $request, Quote $quote, SaveQuote $saveQuote): QuoteResource
    {
        return new QuoteResource($saveQuote->update($quote, $request->validated()));
    }

    public function destroy(Quote $quote, SaveQuote $saveQuote): Response
    {
        Gate::authorize('delete', $quote);

        $saveQuote->delete($quote);

        return response()->noContent();
    }

    /**
     * The reader's quotes narrowed by the favorite, book, type, tag and text-search (q) filters of the request.
     *
     * @return Builder<Quote>
     */
    private function filteredQuotes(Request $request): Builder
    {
        $filters = $request->validate([
            'favorite' => ['nullable', 'boolean'],
            'book' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(QuoteType::class)],
            'q' => ['nullable', 'string', 'min:2', 'max:100'],
            'tag' => ['nullable', 'string', 'max:'.Tag::MAX_LENGTH * 2],
        ]);

        return Quote::query()->whereBelongsTo($request->user())
            ->when($request->boolean('favorite'), fn ($query) => $query->where('is_favorite', true))
            ->when($filters['book'] ?? null, fn ($query, int $book) => $query->where('user_book_id', $book))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['tag'] ?? null, fn ($query, string $tag) => $query->taggedWith($request->user(), Tag::normalize($tag)))
            ->when($filters['q'] ?? null, fn ($query, string $search) => $query->search($search));
    }
}
