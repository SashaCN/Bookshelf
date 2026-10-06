<?php

namespace App\Actions\Social;

use App\Enums\ReportStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportContent
{
    /** Open reports from different readers that hide a post or a comment until a moderator looks at it. */
    public const HIDE_THRESHOLD = 3;

    public function __construct(private readonly ModerateContent $moderation) {}

    /**
     * One report per reader per object. The third open report hides the object; the moderator decides what happens next.
     *
     * @param  array{reason: string, note?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function handle(User $reporter, Post|Comment $content, array $data): Report
    {
        if ($content->user_id === $reporter->id) {
            throw ValidationException::withMessages(['reason' => ['social.cannot_report_own']]);
        }

        $report = $content->reports()->where('reporter_id', $reporter->id)->first();

        if ($report !== null) {
            throw $this->alreadyReported();
        }

        try {
            return DB::transaction(function () use ($reporter, $content, $data): Report {
                // Reports about one object go through here one at a time: whoever comes second waits for the row until
                // the first has committed, so the count below always includes it and the third report always hides.
                $content->newQuery()->whereKey($content->getKey())->lockForUpdate()->firstOrFail();

                $report = Report::query()->create([
                    'reporter_id' => $reporter->id,
                    'reportable_type' => $content->getMorphClass(),
                    'reportable_id' => $content->getKey(),
                    'reason' => $data['reason'],
                    'note' => $data['note'] ?? null,
                ]);

                // The unique index allows one report per reader, so the number of open reports is the number of readers.
                $open = Report::query()
                    ->where('reportable_type', $content->getMorphClass())
                    ->where('reportable_id', $content->getKey())
                    ->where('status', ReportStatus::Open)
                    ->count();

                if ($open >= self::HIDE_THRESHOLD) {
                    $this->moderation->hide($content);
                }

                return $report;
            });
        } catch (UniqueConstraintViolationException) {
            // The same reader twice at the same moment.
            throw $this->alreadyReported();
        }
    }

    private function alreadyReported(): ValidationException
    {
        return ValidationException::withMessages(['reason' => ['social.already_reported']]);
    }
}
