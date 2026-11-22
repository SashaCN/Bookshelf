<?php

namespace App\Actions\Social;

use App\Models\User;
use Illuminate\Support\Collection;

class SearchProfiles
{
    public const LIMIT = 20;

    /**
     * Public readers whose handle starts with the text or whose name contains it, best match first.
     * Each one carries an `is_following` flag, computed with a single extra query.
     *
     * @return Collection<int, User>
     */
    public function handle(User $viewer, string $text): Collection
    {
        $term = mb_strtolower(trim($text));
        $handle = str_starts_with($term, '@') ? substr($term, 1) : $term;
        $handle = $handle === '' ? $term : $handle;

        $prefix = $this->escapeLike($handle).'%';
        $contains = '%'.$this->escapeLike($term).'%';

        $users = User::query()
            ->where('is_public', true)
            ->whereNotNull('username')
            ->whereKeyNot($viewer->id)
            ->where(fn ($query) => $query
                ->where('username', 'like', $prefix)
                ->orWhere('name', 'like', $contains))
            ->orderByRaw('CASE WHEN username = ? THEN 0 WHEN username LIKE ? THEN 1 ELSE 2 END', [$handle, $prefix])
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();

        $followed = $viewer->following()->whereIn('users.id', $users->modelKeys())->pluck('users.id')->all();

        return $users->each(fn (User $user) => $user->setAttribute('is_following', in_array($user->id, $followed, true)));
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
