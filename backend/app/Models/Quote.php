<?php

namespace App\Models;

use App\Enums\QuoteType;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
