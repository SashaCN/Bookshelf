<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\PrintsUserText;
use App\Enums\ReportStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use Illuminate\Console\Command;

class ModerationReports extends Command
{
    use PrintsUserText;

    protected $signature = 'moderation:reports';

    protected $description = 'List the open reports about posts and comments, the oldest first';

    public function handle(): int
    {
        $reports = Report::query()
            ->where('status', ReportStatus::Open)
            ->with(['reporter', 'reportable'])
            ->orderBy('id')
            ->get();

        if ($reports->isEmpty()) {
            $this->components->info('There are no open reports.');

            return self::SUCCESS;
        }

        $this->table(
            ['Report', 'About', 'Reason', 'By', 'Note', 'State', 'Text', 'Reported'],
            $reports->map(fn (Report $report) => [
                $report->id,
                "{$report->reportable_type} {$report->reportable_id}",
                $report->reason->value,
                '@'.$this->safe($report->reporter->handle ?? '?', 30),
                $this->safe($report->note, 40),
                $this->state($report->reportable),
                $report->reportable instanceof Post || $report->reportable instanceof Comment ? $this->safe($report->reportable->content, 50) : '',
                $report->created_at?->toDateTimeString(),
            ])->all(),
        );

        $this->components->info(sprintf('%d open reports. Next: moderation:hide, moderation:restore or moderation:resolve.', $reports->count()));

        return self::SUCCESS;
    }

    private function state(mixed $content): string
    {
        if (! $content instanceof Post && ! $content instanceof Comment) {
            return 'deleted';
        }

        return $content->isHidden() ? 'hidden' : 'visible';
    }
}
