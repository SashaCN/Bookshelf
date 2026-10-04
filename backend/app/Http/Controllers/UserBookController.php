<?php

namespace App\Http\Controllers;

use App\Actions\Library\AddBookToLibrary;
use App\Actions\Library\UpdateUserBook;
use App\Enums\BookSource;
use App\Enums\BookStatus;
use App\Http\Requests\StoreUserBookRequest;
use App\Http\Requests\UpdateUserBookRequest;
use App\Http\Resources\UserBookResource;
use App\Models\UserBook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserBookController extends Controller
{
    /**
     * List the reader's library, newest activity first, optionally filtered by status.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate(['status' => ['nullable', Rule::enum(BookStatus::class)]]);

        $userBooks = $request->user()->userBooks()
            ->with('book.authors')
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('updated_at')
            ->latest('id')
            ->get();

        return UserBookResource::collection($userBooks);
    }

    /**
     * Add a book to the library.
     */
    public function store(StoreUserBookRequest $request, AddBookToLibrary $addBook): UserBookResource
    {
        return new UserBookResource($addBook->handle($request->user(), $request->validated()));
    }

    public function show(UserBook $userBook): UserBookResource
    {
        Gate::authorize('view', $userBook);

        return new UserBookResource($userBook->load('book.authors'));
    }

    /**
     * Change the status, rating or page count of a library entry.
     */
    public function update(UpdateUserBookRequest $request, UserBook $userBook, UpdateUserBook $updateUserBook): UserBookResource
    {
        Gate::authorize('update', $userBook);

        return new UserBookResource($updateUserBook->handle($userBook, $request->validated()));
    }

    /**
     * Remove a book from the library. A hand-entered book nobody else owns is removed with it.
     */
    public function destroy(UserBook $userBook): Response
    {
        Gate::authorize('delete', $userBook);

        DB::transaction(function () use ($userBook): void {
            $book = $userBook->book;
            $userBook->delete();

            if ($book->source === BookSource::Manual && ! $book->userBooks()->exists()) {
                $book->delete();
            }
        });

        return response()->noContent();
    }
}
