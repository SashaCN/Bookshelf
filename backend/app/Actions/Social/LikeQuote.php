<?php

namespace App\Actions\Social;

use App\Enums\QuoteVisibility;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class LikeQuote
{
    /**
     * @throws ModelNotFoundException<Quote>
     */
    public function like(User $user, int $quoteId): void
    {
        $quote = $this->findLikeable($quoteId);

        // One atomic statement: liking twice, even at the same moment, changes nothing.
        DB::table('quote_likes')->insertOrIgnore([
            'user_id' => $user->id,
            'quote_id' => $quote->id,
            'created_at' => now(),
        ]);
    }

    /**
     * An existing like can always be taken back (even if the quote went private since);
     * otherwise the quote must be likeable, so a hidden or missing one stays "not found".
     *
     * @throws ModelNotFoundException<Quote>
     */
    public function unlike(User $user, int $quoteId): void
    {
        $removed = DB::table('quote_likes')
            ->where('user_id', $user->id)
            ->where('quote_id', $quoteId)
            ->delete();

        if ($removed === 0) {
            $this->findLikeable($quoteId);
        }
    }

    /**
     * A quote is likeable when it is public and its owner's profile is public: the same rule as the feed and profiles.
     *
     * @throws ModelNotFoundException<Quote>
     */
    private function findLikeable(int $quoteId): Quote
    {
        return Quote::query()
            ->whereKey($quoteId)
            ->where('visibility', QuoteVisibility::Public)
            ->whereHas('user', fn ($query) => $query->where('is_public', true))
            ->firstOrFail();
    }
}
