<?php

namespace App\Models;

use Database\Factories\ReaderBookmarkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reader_document_id', 'chapter', 'note'])]
class ReaderBookmark extends Model
{
    /** @use HasFactory<ReaderBookmarkFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['chapter' => 'integer'];
    }

    /**
     * @return BelongsTo<ReaderDocument, $this>
     */
    public function readerDocument(): BelongsTo
    {
        return $this->belongsTo(ReaderDocument::class);
    }
}
