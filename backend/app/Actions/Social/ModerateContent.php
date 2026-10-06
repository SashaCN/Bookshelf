<?php

namespace App\Actions\Social;

use App\Enums\ReportStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use Illuminate\Support\Facades\DB;

/**
 * What a moderator (or the third report) does to a post or a comment: hide it from everybody but its author, or give
 * it back. Used by the reports and by the moderation commands.
 */
class ModerateContent
{
    /**
     * @return bool whether anything changed (false when it was hidden already)
     */
    public function hide(Post|Comment $content): bool
    {
        return DB::transaction(function () use ($content): bool {
            $changed = $content->newQuery()->whereKey($content->getKey())->whereNull('hidden_at')->update(['hidden_at' => now()]);

            if ($changed > 0 && $content instanceof Comment) {
                DB::table('posts')->where('id', $content->post_id)->where('comments_count', '>', 0)->decrement('comments_count');
            }

            $content->refresh();

            return $changed > 0;
        });
    }

    /**
     * @return bool whether anything changed (false when it was not hidden)
     */
    public function restore(Post|Comment $content): bool
    {
        return DB::transaction(function () use ($content): bool {
            $changed = $content->newQuery()->whereKey($content->getKey())->whereNotNull('hidden_at')->update(['hidden_at' => null]);

            if ($changed > 0 && $content instanceof Comment) {
                DB::table('posts')->where('id', $content->post_id)->increment('comments_count');
            }

            $content->refresh();

            return $changed > 0;
        });
    }

    /**
     * Mark the open reports about the content as looked at, so they stop counting towards hiding it again.
     *
     * @return int how many reports were open
     */
    public function resolveReports(Post|Comment $content): int
    {
        return Report::query()
            ->where('reportable_type', $content->getMorphClass())
            ->where('reportable_id', $content->getKey())
            ->where('status', ReportStatus::Open)
            ->update(['status' => ReportStatus::Resolved]);
    }
}
