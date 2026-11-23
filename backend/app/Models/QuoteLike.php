<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row of the quote_likes pivot, for counting and checking likes without joining users.
 */
class QuoteLike extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
