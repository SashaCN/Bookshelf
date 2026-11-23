<?php

namespace App\Models;

use Database\Factories\BookTextFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The text of a book the reader uploaded, kept as sanitized chapters (the original file is never stored),
 * together with the place the reader has got to.
 */
#[Fillable([
    'user_book_id',
    'user_id',
    'source_name',
    'chapters_count',
    'characters_count',
    'chapter_position',
    'chapter_progress',
    'progress',
    'position_updated_at',
])]
class BookText extends Model
{
    /** @use HasFactory<BookTextFactory> */
    use HasFactory;

    /**
     * Defaults of a freshly created text, mirroring the column defaults, so a new model is complete before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'chapter_position' => 0,
        'chapter_progress' => 0,
        'progress' => 0,
    ];

    protected function casts(): array
    {
        return [
            'chapters_count' => 'integer',
            'characters_count' => 'integer',
            'chapter_position' => 'integer',
            'chapter_progress' => 'float',
            'progress' => 'float',
            'position_updated_at' => 'datetime',
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
     * @return HasMany<BookChapter, $this>
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(BookChapter::class);
    }

    /**
     * The table of contents: the chapters without their (large) content.
     *
     * @return HasMany<BookChapter, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(BookChapter::class)
            ->select(['id', 'book_text_id', 'position', 'title', 'characters'])
            ->orderBy('position');
    }

    /**
     * The quotes saved from this text.
     *
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
