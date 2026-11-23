<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'timezone', 'username', 'is_public'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const DEFAULT_TIMEZONE = 'Europe/Kyiv';

    public const NEW_FOLLOWERS_CAP = 99;

    /**
     * Defaults of a freshly created reader, mirroring the column defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_public' => false,
    ];

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
     * @return HasMany<ReaderDocument, $this>
     */
    public function readerDocuments(): HasMany
    {
        return $this->hasMany(ReaderDocument::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'follower_id', 'followed_id')->withPivot('created_at');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'followed_id', 'follower_id')->withPivot('created_at');
    }

    /**
     * Public followers who followed after the reader last looked at the list, capped at NEW_FOLLOWERS_CAP.
     */
    public function newFollowersCount(): int
    {
        $count = $this->followers()
            ->where('users.is_public', true)
            ->when($this->followers_seen_at, fn ($query, $seenAt) => $query->where('follows.created_at', '>', $seenAt))
            ->count();

        return min($count, self::NEW_FOLLOWERS_CAP);
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
            'is_public' => 'boolean',
            'followers_seen_at' => 'datetime',
        ];
    }
}
