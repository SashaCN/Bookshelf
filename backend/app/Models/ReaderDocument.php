<?php

namespace App\Models;

use Database\Factories\ReaderDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'title',
    'author',
    'path',
    'chapters',
    'chapters_count',
    'position_chapter',
    'position_fraction',
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
            'chapters_count' => 'integer',
            'position_chapter' => 'integer',
            'position_fraction' => 'float',
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
     * How far the reader is through the whole book, in percent.
     */
    public function progress(): float
    {
        if ($this->chapters_count === 0) {
            return 0.0;
        }

        return round(($this->position_chapter + $this->position_fraction) / $this->chapters_count * 100, 1);
    }
}
