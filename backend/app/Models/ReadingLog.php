<?php

namespace App\Models;

use Database\Factories\ReadingLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change of the bookmark. A correction (300 instead of 30, then 30 again) is a log entry
 * with a negative change, so the net sum of a day stays honest.
 */
#[Fillable([
    'user_book_id',
    'user_id',
    'from_page',
    'to_page',
    'logged_on',
])]
class ReadingLog extends Model
{
    /** @use HasFactory<ReadingLogFactory> */
    use HasFactory;

    /** Log entries are never edited, so there is no "updated at". */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_page' => 'integer',
            'to_page' => 'integer',
            'logged_on' => 'date',
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

    /** Pages read by this change; negative for a correction downwards. */
    public function pages(): int
    {
        return $this->to_page - $this->from_page;
    }
}
