<?php

namespace App\Actions\Social;

use App\Enums\QuoteVisibility;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProfileQuery
{
    public const PER_PAGE = 20;

    /**
     * The public quotes of one reader, newest first.
     *
     * @return Paginator<int, Quote>
     */
    public function quotes(User $profile, ?User $viewer = null): Paginator
    {
        return $profile->quotes()
            ->where('visibility', QuoteVisibility::Public)
            ->withLikes($viewer)
            ->with('userBook.book.authors')
            ->latest('id')
            ->simplePaginate(self::PER_PAGE);
    }

    /**
     * Public quotes of the readers the viewer follows, newest first, each with its author.
     *
     * @return Paginator<int, Quote>
     */
    public function feed(User $viewer): Paginator
    {
        return Quote::query()
            ->where('visibility', QuoteVisibility::Public)
            ->whereIn('user_id', $viewer->following()->where('users.is_public', true)->select('users.id'))
            ->withLikes($viewer)
            ->with(['user', 'userBook.book.authors'])
            ->latest('id')
            ->simplePaginate(self::PER_PAGE);
    }

    /**
     * @return Paginator<int, User&object{pivot: Pivot}>
     */
    public function followers(User $profile): Paginator
    {
        return $profile->followers()->where('users.is_public', true)
            ->orderByDesc('follows.created_at')->orderByDesc('users.id')
            ->simplePaginate(self::PER_PAGE);
    }

    /**
     * @return Paginator<int, User&object{pivot: Pivot}>
     */
    public function following(User $profile): Paginator
    {
        return $profile->following()->where('users.is_public', true)
            ->orderByDesc('follows.created_at')->orderByDesc('users.id')
            ->simplePaginate(self::PER_PAGE);
    }
}
