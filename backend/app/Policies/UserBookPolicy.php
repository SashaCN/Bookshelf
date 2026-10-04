<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserBook;
use Illuminate\Auth\Access\Response;

/**
 * A library entry belongs to one user. Everybody else gets "not found", so the API
 * never confirms that somebody else's entry exists.
 */
class UserBookPolicy
{
    public function view(User $user, UserBook $userBook): Response
    {
        return $this->owns($user, $userBook);
    }

    public function update(User $user, UserBook $userBook): Response
    {
        return $this->owns($user, $userBook);
    }

    public function delete(User $user, UserBook $userBook): Response
    {
        return $this->owns($user, $userBook);
    }

    private function owns(User $user, UserBook $userBook): Response
    {
        return $user->id === $userBook->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
