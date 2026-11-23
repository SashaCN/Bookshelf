<?php

namespace App\Models;

use App\Enums\PostType;
use App\Models\Concerns\VisibleToReaders;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'user_id',
    'book_id',
    'type',
    'content',
    'comment',
    'rating',
    'page',
    'hidden_at',
])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, VisibleToReaders;

    /**
     * Defaults of a freshly created post, mirroring the column defaults, so a new model is complete before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'likes_count' => 0,
        'comments_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => PostType::class,
            'rating' => 'integer',
            'page' => 'integer',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'hidden_at' => 'datetime',
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
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function likedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'likes', 'post_id', 'user_id');
    }

    /**
     * Everything a post needs to be shown to the viewer: who wrote it, about which book, whether the viewer has liked
     * it, and how many comments the viewer can see (hidden and blocked ones do not count for them).
     *
     * @param  Builder<Post>  $query
     */
    public function scopeWithViewerState(Builder $query, User $viewer): void
    {
        $query
            ->with(['user', 'book.authors'])
            ->withExists(['likedBy as liked_by_me' => fn ($likes) => $likes->where('likes.user_id', $viewer->id)])
            ->withCount(['comments as visible_comments_count' => fn ($comments) => $comments->visibleTo($viewer)]);
    }

    /**
     * @return MorphMany<Report, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}
