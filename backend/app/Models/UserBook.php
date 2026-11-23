<?php

namespace App\Models;

use App\Enums\BookStatus;
use App\Enums\QuoteVisibility;
use Database\Factories\UserBookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'book_id',
    'status',
    'current_page',
    'total_pages',
    'rating',
    'review',
    'review_visibility',
    'reviewed_at',
    'started_at',
    'finished_at',
])]
class UserBook extends Model
{
    /** @use HasFactory<UserBookFactory> */
    use HasFactory;

    /**
     * Defaults of a freshly created entry, mirroring the column defaults, so a new model is complete before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'want',
        'current_page' => 0,
        'review_visibility' => 'private',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookStatus::class,
            'current_page' => 'integer',
            'total_pages' => 'integer',
            'rating' => 'integer',
            'review_visibility' => QuoteVisibility::class,
            'reviewed_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * @return HasMany<ReadingLog, $this>
     */
    public function readingLogs(): HasMany
    {
        return $this->hasMany(ReadingLog::class);
    }

    /**
     * Archived finished passes, newest first.
     *
     * @return HasMany<UserBookRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(UserBookRead::class)->orderByDesc('finished_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * The linked EPUB the reader opened most recently (never-opened ones last, then the newest).
     *
     * @return HasOne<ReaderDocument, $this>
     */
    public function readerDocument(): HasOne
    {
        return $this->hasOne(ReaderDocument::class)
            ->orderByRaw('last_read_at IS NULL')
            ->orderByDesc('last_read_at')
            ->orderByDesc('id');
    }

    public function progressPercent(): ?int
    {
        if (! $this->total_pages) {
            return null;
        }

        return (int) floor($this->current_page / $this->total_pages * 100);
    }
}
