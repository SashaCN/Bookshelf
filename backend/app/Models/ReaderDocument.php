<?php

namespace App\Models;

use Database\Factories\ReaderDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'user_book_id',
    'title',
    'author',
    'path',
    'chapters',
    'chapter_titles',
    'chapters_count',
    'position_chapter',
    'position_fraction',
    'last_read_at',
])]
class ReaderDocument extends Model
{
    /** @use HasFactory<ReaderDocumentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position_chapter' => 0,
        'position_fraction' => 0,
    ];

    protected function casts(): array
    {
        return [
            'chapters' => 'array',
            'chapter_titles' => 'array',
            'chapters_count' => 'integer',
            'position_chapter' => 'integer',
            'position_fraction' => 'float',
            'last_read_at' => 'datetime',
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
     * The library entry this document is linked to, if any. Only a link: nothing is synced between them.
     *
     * @return BelongsTo<UserBook, $this>
     */
    public function userBook(): BelongsTo
    {
        return $this->belongsTo(UserBook::class);
    }

    /**
     * How far the reader is through the whole book, in percent.
     */
    public function progress(): float
    {
        if ($this->chapters_count === 0) {
            return 0.0;
        }

        return round(($this->position_chapter + $this->position_fraction) / $this->chapters_count * 100, 1);
    }

    /**
     * Chapters that have a title in the table of contents, in spine order.
     *
     * @return list<array{index: int, title: string}>
     */
    public function toc(): array
    {
        $toc = [];
        foreach ($this->chapter_titles ?? [] as $index => $title) {
            if (is_string($title) && $title !== '') {
                $toc[] = ['index' => $index, 'title' => $title];
            }
        }

        return $toc;
    }
}
