<?php

namespace App\Actions\Quotes;

use App\Models\Quote;
use App\Models\User;

class PickQuoteOfTheDay
{
    /**
     * One of the reader's quotes, the same all day (in their own time zone) and another one tomorrow.
     * The pick is derived from the date, so nothing has to be stored and a reload does not shuffle it.
     */
    public function handle(User $user, bool $onlyFavorites = false): ?Quote
    {
        $quotes = $user->quotes()->when($onlyFavorites, fn ($query) => $query->where('is_favorite', true));

        $count = (clone $quotes)->count();

        if ($count === 0) {
            return null;
        }

        $day = now($user->timezone)->toDateString();
        $offset = crc32("{$user->id}:{$day}") % $count;

        return $quotes->with('userBook.book.authors')->orderBy('id')->offset($offset)->limit(1)->first();
    }
}
