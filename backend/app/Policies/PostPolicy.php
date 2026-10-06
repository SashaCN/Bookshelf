<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A post can be seen unless moderation hid it (its author still sees it) or its author and the viewer have blocked
 * each other. Only the author may change or delete it. Everybody else gets "not found", so the API never confirms
 * that a post they may not see exists.
 */
class PostPolicy
{
    public function view(User $user, Post $post): Response
    {
        return $this->canSee($user, $post) ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Post $post): Response
    {
        return $this->isAuthor($user, $post);
    }

    public function delete(User $user, Post $post): Response
    {
        return $this->isAuthor($user, $post);
    }

    /**
     * Whether the viewer may see the post. Also used by the comments, which are only as visible as their post.
     */
    public function canSee(User $user, Post $post): bool
    {
        if ($user->id === $post->user_id) {
            return true;
        }

        return ! $post->isHidden() && ! $user->isBlockedWith($post->user_id);
    }

    private function isAuthor(User $user, Post $post): Response
    {
        return $user->id === $post->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
