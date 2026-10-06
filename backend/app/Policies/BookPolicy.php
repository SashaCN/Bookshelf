<?php

namespace App\Policies;

use App\Enums\BookSource;
use App\Models\Book;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * The catalog of Open Library is shared; a book entered by hand is visible to the reader who entered it alone.
 */
class BookPolicy
{
    public function view(User $user, Book $book): Response
    {
        return $book->source !== BookSource::Manual || $book->created_by === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
