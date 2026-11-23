<?php

namespace App\Actions\Account;

use App\Enums\ReportableType;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeleteAccount
{
    /**
     * Delete the user and everything that belongs to them, in one transaction, so a failure never leaves a
     * half-deleted account. Foreign keys cascade the user's posts (and the comments and likes under them),
     * comments, likes, follows, blocks, reports they filed, library, quotes, goals and book texts. Three things
     * they do not cover are done here:
     *  - the counters of other readers' posts that the user liked or commented on;
     *  - the reports about the deleted posts and comments (a report points at two tables, so it has no foreign key);
     *  - the sessions and reset tokens (neither table has a foreign key).
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            // Posts of other readers that carry something of the user's. Their counters are kept by the application,
            // so the cascade would leave them too high.
            $likedPostIds = DB::table('likes')->where('user_id', $user->id)->pluck('post_id');
            $commentedPostIds = Comment::query()->where('user_id', $user->id)->pluck('post_id');
            $affectedPostIds = $likedPostIds->merge($commentedPostIds)->unique()->values();

            $this->deleteReportsAbout($user);

            // Neither table has a foreign key: other devices must not keep a session, nor an old reset link a live token.
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $user->delete();

            $this->recountPosts($affectedPostIds);
        });
    }

    /**
     * Reports whose subject disappears with the user: their posts, their comments, and the comments of others under
     * their posts. (The reports the user filed go with the user, by foreign key.)
     */
    private function deleteReportsAbout(User $user): void
    {
        $postIds = Post::query()->where('user_id', $user->id)->pluck('id');
        $commentIds = Comment::query()
            ->where('user_id', $user->id)
            ->orWhereIn('post_id', $postIds)
            ->pluck('id');

        DB::table('reports')->where('reportable_type', ReportableType::Post->value)->whereIn('reportable_id', $postIds)->delete();
        DB::table('reports')->where('reportable_type', ReportableType::Comment->value)->whereIn('reportable_id', $commentIds)->delete();
    }

    /**
     * Sets the counters of the posts back to what is really there: all likes, and the comments that are not hidden.
     *
     * @param  Collection<int, int>  $postIds
     */
    private function recountPosts(Collection $postIds): void
    {
        foreach ($postIds->chunk(500) as $chunk) {
            DB::table('posts')->whereIn('id', $chunk)->update([
                'likes_count' => DB::raw('(SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id)'),
                'comments_count' => DB::raw('(SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id AND comments.hidden_at IS NULL)'),
            ]);
        }
    }
}
