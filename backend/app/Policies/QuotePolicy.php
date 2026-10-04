<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A quote belongs to one reader. Everybody else gets "not found", so the API
 * never confirms that somebody else's quote exists.
 */
class QuotePolicy
{
    public function view(User $user, Quote $quote): Response
    {
        return $this->owns($user, $quote);
    }

    public function update(User $user, Quote $quote): Response
    {
        return $this->owns($user, $quote);
    }

    public function delete(User $user, Quote $quote): Response
    {
        return $this->owns($user, $quote);
    }

    private function owns(User $user, Quote $quote): Response
    {
        return $user->id === $quote->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
