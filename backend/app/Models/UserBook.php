<?php

namespace App\Models;

use App\Enums\BookStatus;
use Database\Factories\UserBookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'book_id',
    'status',
    'current_page',
    'total_pages',
    'rating',
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
    ];

    protected function casts(): array
    {
        return [
            'status' => BookStatus::class,
            'current_page' => 'integer',
            'total_pages' => 'integer',
            'rating' => 'integer',
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
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function progressPercent(): ?int
    {
        if (! $this->total_pages) {
            return null;
        }

        return (int) floor($this->current_page / $this->total_pages * 100);
    }
}
