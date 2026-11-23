<?php

namespace App\Actions\Social;

use App\Enums\PostType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class PublishPost
{
    /**
     * @param  array<string, mixed>  $data  validated input of StorePostRequest
     *
     * @throws ValidationException
     */
    public function handle(User $author, array $data): Post
    {
        return match (PostType::from($data['type'])) {
            PostType::Quote => $this->publishQuote($author, $data),
            PostType::Review => $this->publishReview($author, $data),
        };
    }

    /**
     * A snapshot: the text is copied, so editing or deleting the quote later changes nothing here.
     *
     * @param  array<string, mixed>  $data
     */
    private function publishQuote(User $author, array $data): Post
    {
        $quote = $author->quotes()->with('userBook')->findOrFail($data['quote_id']);

        return $author->posts()->create([
            'book_id' => $quote->userBook->book_id,
            'type' => PostType::Quote,
            'content' => $quote->content,
            'comment' => $data['comment'] ?? null,
            'page' => $quote->page,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function publishReview(User $author, array $data): Post
    {
        $userBook = $author->userBooks()->findOrFail($data['user_book_id']);

        $alreadyReviewed = $author->posts()->where('type', PostType::Review)->where('book_id', $userBook->book_id)->exists();

        if ($alreadyReviewed) {
            throw $this->reviewExists();
        }

        try {
            return $author->posts()->create([
                'book_id' => $userBook->book_id,
                'type' => PostType::Review,
                'content' => $data['content'] ?? null,
                'rating' => $data['rating'],
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two requests at the same moment: the unique index lets only one review through.
            throw $this->reviewExists();
        }
    }

    private function reviewExists(): ValidationException
    {
        return ValidationException::withMessages(['user_book_id' => ['social.review_exists']]);
    }
}
