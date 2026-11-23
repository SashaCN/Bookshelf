<?php

namespace App\Models;

use App\Enums\QuoteType;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'user_book_id',
    'user_id',
    'type',
    'content',
    'note',
    'page',
    'is_favorite',
    'book_text_id',
    'chapter_position',
    'start_offset',
    'end_offset',
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
    ];

    protected function casts(): array
    {
        return [
            'type' => QuoteType::class,
            'page' => 'integer',
            'is_favorite' => 'boolean',
            'chapter_position' => 'integer',
            'start_offset' => 'integer',
            'end_offset' => 'integer',
        ];
    }

    /**
     * The text this quote was selected from, when it was saved from the reader.
     *
     * @return BelongsTo<BookText, $this>
     */
    public function bookText(): BelongsTo
    {
        return $this->belongsTo(BookText::class);
    }

    /**
     * Where the quote sits in its text: chapter and character offsets, or null for a quote typed in by hand
     * (or one whose text has been removed).
     *
     * @return array{book_text_id: int, chapter: int, start: int, end: int}|null
     */
    public function anchor(): ?array
    {
        if ($this->book_text_id === null || $this->chapter_position === null || $this->start_offset === null || $this->end_offset === null) {
            return null;
        }

        return [
            'book_text_id' => $this->book_text_id,
            'chapter' => $this->chapter_position,
            'start' => $this->start_offset,
            'end' => $this->end_offset,
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
     * The reader's private labels on this quote. Never part of a Post snapshot or any public response.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'quote_tag')->orderBy('tags.name');
    }

    /**
     * The reader's quotes carrying the tag of that name (tags are private, so the name only means something per reader).
     *
     * @param  Builder<Quote>  $query
     */
    #[Scope]
    protected function taggedWith(Builder $query, User $owner, string $name): void
    {
        $query->whereIn('quotes.id', DB::table('quote_tag')
            ->join('tags', 'tags.id', '=', 'quote_tag.tag_id')
            ->where('tags.user_id', $owner->id)
            ->where('tags.name', $name)
            ->select('quote_tag.quote_id'));
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
}
