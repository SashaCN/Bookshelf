<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A private label of one reader, attached to their quotes.
 */
#[Fillable(['user_id', 'name'])]
class Tag extends Model
{
    public const MAX_LENGTH = 40;

    public const MAX_PER_QUOTE = 10;

    /**
     * The canonical spelling of a tag: trimmed, runs of whitespace collapsed to one space, lowercase.
     */
    public static function normalize(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Quote, $this>
     */
    public function quotes(): BelongsToMany
    {
        return $this->belongsToMany(Quote::class, 'quote_tag');
    }
}
