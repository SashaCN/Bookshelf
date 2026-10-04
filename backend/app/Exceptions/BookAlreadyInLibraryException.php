<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class BookAlreadyInLibraryException extends RuntimeException
{
    public function __construct(public readonly int $userBookId)
    {
        parent::__construct('library.already_added');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'library.already_added',
            'user_book_id' => $this->userBookId,
        ], 409);
    }
}
