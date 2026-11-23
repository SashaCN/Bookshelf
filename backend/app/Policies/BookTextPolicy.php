<?php

namespace App\Policies;

use App\Models\BookText;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * The text of a book belongs to the reader of the library entry it was uploaded for. Everybody else gets
 * "not found", so the API never confirms that somebody else's text exists.
 */
class BookTextPolicy
{
    public function view(User $user, BookText $bookText): Response
    {
        return $this->owns($user, $bookText);
    }

    public function update(User $user, BookText $bookText): Response
    {
        return $this->owns($user, $bookText);
    }

    public function delete(User $user, BookText $bookText): Response
    {
        return $this->owns($user, $bookText);
    }

    private function owns(User $user, BookText $bookText): Response
    {
        return $user->id === $bookText->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
