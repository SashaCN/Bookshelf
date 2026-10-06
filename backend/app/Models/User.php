<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'handle', 'bio', 'password', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const DEFAULT_TIMEZONE = 'Europe/Kyiv';

    /**
     * @return HasMany<UserBook, $this>
     */
    public function userBooks(): HasMany
    {
        return $this->hasMany(UserBook::class);
    }

    /**
     * @return HasMany<ReadingGoal, $this>
     */
    public function readingGoals(): HasMany
    {
        return $this->hasMany(ReadingGoal::class);
    }

    /**
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * The readers this reader follows.
     *
     * @return BelongsToMany<User, $this>
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'follower_id', 'followed_id');
    }

    /**
     * The readers who follow this reader.
     *
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'followed_id', 'follower_id');
    }

    /**
     * The readers this reader has blocked.
     *
     * @return BelongsToMany<User, $this>
     */
    public function blockedReaders(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'blocks', 'user_id', 'blocked_id')->withPivot('created_at');
    }

    /**
     * The readers who have blocked this reader.
     *
     * @return BelongsToMany<User, $this>
     */
    public function blockers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'blocks', 'blocked_id', 'user_id');
    }

    /**
     * Whether one of the two has blocked the other. Blocking is mutual: neither sees what the other writes.
     */
    public function isBlockedWith(int $readerId): bool
    {
        return DB::table('blocks')
            ->where(fn ($query) => $query->where('user_id', $this->id)->where('blocked_id', $readerId))
            ->orWhere(fn ($query) => $query->where('user_id', $readerId)->where('blocked_id', $this->id))
            ->exists();
    }

    /**
     * Readers the viewer has no block with, in either direction. Others never turn up in search results or counts.
     *
     * @param  Builder<User>  $query
     */
    public function scopeNotBlockedWith(Builder $query, User $viewer): void
    {
        self::excludeBlocked($query, $viewer, $query->qualifyColumn('id'));
    }

    /**
     * What a profile page shows to the viewer: the number of posts, followers and followed readers, and how the viewer
     * stands with the reader. Hidden posts and readers the viewer has a block with are left out of the numbers.
     *
     * @param  Builder<User>  $query
     */
    public function scopeWithProfileState(Builder $query, User $viewer): void
    {
        $followers = DB::table('follows')->selectRaw('count(*)')->whereColumn('follows.followed_id', 'users.id');
        self::excludeBlocked($followers, $viewer, 'follows.follower_id');

        $following = DB::table('follows')->selectRaw('count(*)')->whereColumn('follows.follower_id', 'users.id');
        self::excludeBlocked($following, $viewer, 'follows.followed_id');

        $query->addSelect([
            'posts_count' => Post::query()->selectRaw('count(*)')->whereColumn('posts.user_id', 'users.id')->visibleTo($viewer),
            'followers_count' => $followers,
            'following_count' => $following,
        ])->withExists([
            'followers as is_following' => fn ($follows) => $follows->where('follows.follower_id', $viewer->id),
            'blockers as is_blocked' => fn ($blocks) => $blocks->where('blocks.user_id', $viewer->id),
        ]);
    }

    /**
     * Narrow a query to rows whose reader (`$column`) has no block with the viewer, in either direction.
     *
     * @param  Builder<*>|\Illuminate\Database\Query\Builder  $query
     */
    public static function excludeBlocked(Builder|QueryBuilder $query, User $viewer, string $column): void
    {
        $query
            ->whereNotIn($column, fn ($blocked) => $blocked->select('blocked_id')->from('blocks')->where('user_id', $viewer->id))
            ->whereNotIn($column, fn ($blockers) => $blockers->select('user_id')->from('blocks')->where('blocked_id', $viewer->id));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
}
