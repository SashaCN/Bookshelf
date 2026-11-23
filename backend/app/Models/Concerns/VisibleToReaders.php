<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * For content that has an author (`user_id`) and can be hidden by moderation (`hidden_at`).
 *
 * @mixin Model
 */
trait VisibleToReaders
{
    /**
     * What the viewer may see: nothing hidden by moderation (except their own, which they see flagged), and nothing
     * written by a reader they have a block with, in either direction.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        $author = $query->qualifyColumn('user_id');

        $query->where(
            fn (Builder $visible) => $visible->whereNull($query->qualifyColumn('hidden_at'))->orWhere($author, $viewer->id),
        );

        User::excludeBlocked($query, $viewer, $author);
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }
}
