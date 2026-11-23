<?php

namespace App\Actions\Social;

use App\Models\Quote;
use App\Models\QuoteComment;
use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class QuoteComments
{
    public const PER_PAGE = 20;

    /**
     * Comments of a quote, oldest first, without those of readers blocked either way. Guests may read.
     *
     * @return Paginator<int, QuoteComment>
     *
     * @throws ModelNotFoundException<Quote>
     */
    public function list(?User $viewer, int $quoteId): Paginator
    {
        $quote = $this->findCommentable($viewer, $quoteId);

        return $quote->comments()
            ->visibleTo($viewer)
            ->with('user')
            ->orderBy('id')
            ->simplePaginate(self::PER_PAGE)
            // The owner is already known, so can_delete needs no query per comment.
            ->through(fn (QuoteComment $comment) => $comment->setRelation('quote', $quote));
    }

    /**
     * @throws ModelNotFoundException<Quote>
     */
    public function post(User $user, int $quoteId, string $body): QuoteComment
    {
        $quote = $this->findCommentable($user, $quoteId);

        $comment = $quote->comments()->create(['user_id' => $user->id, 'body' => $body]);

        return $comment->setRelation('user', $user)->setRelation('quote', $quote);
    }

    /**
     * Only the author of a comment or the owner of the quote may remove it; anybody else gets "not found".
     *
     * @throws ModelNotFoundException<QuoteComment>
     */
    public function delete(User $user, int $quoteId, int $commentId): void
    {
        $quote = $this->findCommentable($user, $quoteId);

        $comment = $quote->comments()->whereKey($commentId)->visibleTo($user)->firstOrFail();

        abort_unless($comment->user_id === $user->id || $quote->user_id === $user->id, 404);

        $comment->delete();
    }

    /**
     * @throws ModelNotFoundException<Quote>
     */
    private function findCommentable(?User $viewer, int $quoteId): Quote
    {
        return Quote::query()->whereKey($quoteId)->sharedWith($viewer)->firstOrFail();
    }
}
