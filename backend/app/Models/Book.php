<?php

namespace App\Models;

use App\Enums\BookSource;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'subtitle',
    'isbn_13',
    'openlibrary_work_key',
    'cover_url',
    'page_count',
    'published_year',
    'subjects',
    'source',
    'created_by',
])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'subjects' => 'array',
            'source' => BookSource::class,
            'page_count' => 'integer',
            'published_year' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Author, $this>
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class)->withPivot('position')->orderByPivot('position');
    }

    /**
     * @return HasMany<UserBook, $this>
     */
    public function userBooks(): HasMany
    {
        return $this->hasMany(UserBook::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
