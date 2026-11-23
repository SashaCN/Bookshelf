<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reader_document_id', 'chapter', 'read_on'])]
class ReaderChapterRead extends Model
{
    public const UPDATED_AT = null;

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
