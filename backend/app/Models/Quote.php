<?php

namespace App\Models;

use App\Enums\QuoteType;
use App\Enums\QuoteVisibility;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'user_book_id',
    'user_id',
    'type',
    'content',
    'note',
    'page',
    'is_favorite',
    'visibility',
])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /**
     * Defaults of a freshly created quote, mirroring the column defaults, so a new model is complete before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'quote',
        'is_favorite' => false,
        'visibility' => 'private',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuoteType::class,
            'page' => 'integer',
            'is_favorite' => 'boolean',
            'visibility' => QuoteVisibility::class,
        ];
    }

    /**
     * @return BelongsTo<UserBook, $this>
     */
    public function userBook(): BelongsTo
    {
        return $this->belongsTo(UserBook::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'quote_likes')->withPivot('created_at');
    }

    /**
     * The like rows themselves: counting them does not join users, which the planner would otherwise drive from.
     *
     * @return HasMany<QuoteLike, $this>
     */
    public function likeRows(): HasMany
    {
        return $this->hasMany(QuoteLike::class);
    }

    /**
     * @return HasMany<QuoteComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(QuoteComment::class);
    }

    /**
     * Quotes whose text or note contains every word of the search (FULLTEXT index with the ngram parser), most relevant
     * first. The input is reduced to letters, digits and spaces, so it can never carry boolean-mode operators; words of
     * one character cannot be found by the index, so a search made only of such words falls back to LIKE.
     *
     * @param  Builder<Quote>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $search): void
    {
        $clean = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $search));
        $words = array_values(array_filter(explode(' ', $clean), fn (string $word) => mb_strlen($word) >= 2));

        if ($words === []) {
            // Only letters and digits are left, so there is nothing to escape.
            $like = "%{$clean}%";
            $query->where(fn (Builder $inner) => $inner->where('content', 'like', $like)->orWhere('note', 'like', $like));

            if ($clean === '') {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        $boolean = implode(' ', array_map(fn (string $word) => '+'.$word, $words));

        $query
            ->whereRaw('MATCH (content, note) AGAINST (? IN BOOLEAN MODE)', [$boolean])
            ->orderByRaw('MATCH (content, note) AGAINST (? IN BOOLEAN MODE) DESC', [$boolean]);
    }

    /**
     * Quotes the viewer may like and comment on: public, from a public profile, with no block either way.
     * The same rule as the feed and profiles.
     *
     * @param  Builder<Quote>  $query
     */
    #[Scope]
    protected function sharedWith(Builder $query, ?User $viewer): void
    {
        $query
            ->where('visibility', QuoteVisibility::Public)
            ->whereHas('user', fn ($user) => $user->where('is_public', true)->visibleTo($viewer));
    }

    /**
     * Adds comments_count: the comments the viewer can see (not those of readers blocked either way), in the same query.
     * A correlated subquery with two small NOT IN lookups per quote on the (quote_id, id) index, so it stays cheap.
     *
     * @param  Builder<Quote>  $query
     */
    #[Scope]
    protected function withComments(Builder $query, ?User $viewer = null): void
    {
        $query->withCount(['comments' => fn ($comments) => $comments->visibleTo($viewer)]);
    }

    /**
     * Adds likes_count (without likes of readers blocked either way, like comments_count) and, for a signed-in viewer,
     * liked_by_me, in the same query (no query per quote).
     *
     * @param  Builder<Quote>  $query
     */
    #[Scope]
    protected function withLikes(Builder $query, ?User $viewer = null): void
    {
        $query->withCount(['likeRows as likes_count' => function ($likes) use ($viewer): void {
            if ($viewer !== null) {
                $likes
                    ->whereNotIn('quote_likes.user_id', DB::table('user_blocks')->where('blocker_id', $viewer->id)->select('blocked_id'))
                    ->whereNotIn('quote_likes.user_id', DB::table('user_blocks')->where('blocked_id', $viewer->id)->select('blocker_id'));
            }
        }]);

        if ($viewer !== null) {
            $query->withExists(['likeRows as liked_by_me' => fn ($likes) => $likes->where('quote_likes.user_id', $viewer->id)]);
        }
    }
}
