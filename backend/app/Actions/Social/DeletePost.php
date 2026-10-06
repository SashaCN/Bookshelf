<?php

namespace App\Actions\Social;

use App\Enums\ReportableType;
use App\Models\Post;
use App\Models\Report;
use Illuminate\Support\Facades\DB;

class DeletePost
{
    /**
     * Comments and likes go with the post; so do the reports about it and about its comments, which would point nowhere.
     * The quote the post was made from is not touched.
     */
    public function handle(Post $post): void
    {
        DB::transaction(function () use ($post): void {
            Report::query()
                ->where('reportable_type', ReportableType::Comment->value)
                ->whereIn('reportable_id', $post->comments()->select('id'))
                ->delete();

            Report::query()
                ->where('reportable_type', ReportableType::Post->value)
                ->where('reportable_id', $post->id)
                ->delete();

            $post->delete();
        });
    }
}
