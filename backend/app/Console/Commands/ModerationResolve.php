<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\PrintsUserText;
use App\Enums\ReportStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use Illuminate\Console\Command;

class ModerationResolve extends Command
{
    use PrintsUserText;

    protected $signature = 'moderation:resolve {report : The id of the report}';

    protected $description = 'Mark one report as looked at, without changing whether its object is hidden';

    public function handle(): int
    {
        $id = (string) $this->argument('report');

        if (! ctype_digit($id)) {
            $this->components->error('The id must be a whole number, "'.$this->safe($id, 30).'" is not.');

            return self::FAILURE;
        }

        $report = Report::query()->find($id);

        if ($report === null) {
            $this->components->error("There is no report with id {$id}.");

            return self::FAILURE;
        }

        if ($report->status === ReportStatus::Resolved) {
            $this->components->info('It was resolved already.');

            return self::SUCCESS;
        }

        $report->update(['status' => ReportStatus::Resolved]);

        $this->components->info('Resolved.');
        $this->warnWhenStillHidden($report);

        return self::SUCCESS;
    }

    /**
     * Closing the last open report of a hidden post or comment does not show it again, and nothing else will:
     * the one who resolves reports must know it stays hidden, and how to bring it back.
     */
    private function warnWhenStillHidden(Report $report): void
    {
        $content = $report->reportable;

        if (! $content instanceof Post && ! $content instanceof Comment) {
            return;
        }

        $stillOpen = Report::query()
            ->where('reportable_type', $report->reportable_type)
            ->where('reportable_id', $report->reportable_id)
            ->where('status', ReportStatus::Open)
            ->exists();

        if ($stillOpen || ! $content->isHidden()) {
            return;
        }

        $this->components->warn(
            "That was the last open report about {$report->reportable_type} {$report->reportable_id}, which is still hidden. "
            ."Resolving a report does not show it again: run \"php artisan moderation:restore {$report->reportable_type} {$report->reportable_id}\" to restore it, "
            .'or leave it hidden.',
        );
    }
}
