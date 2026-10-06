<?php

namespace App\Models;

use Database\Factories\BookChapterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One chapter of an uploaded text: sanitized HTML and the length of its text (see App\Services\Epub\Text).
 */
#[Fillable([
    'book_text_id',
    'position',
    'title',
    'content',
    'characters',
])]
class BookChapter extends Model
{
    /** @use HasFactory<BookChapterFactory> */
    use HasFactory;

    /** A chapter never changes after the import. */
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'characters' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BookText, $this>
     */
    public function bookText(): BelongsTo
    {
        return $this->belongsTo(BookText::class);
    }
}
