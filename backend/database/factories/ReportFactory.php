<?php

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * A report about a post by default; use about() to point it at a post or a comment.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reportable_type' => 'post',
            'reportable_id' => Post::factory(),
            'reason' => ReportReason::Spam,
            'note' => null,
            'status' => ReportStatus::Open,
        ];
    }

    public function about(Post|Comment $reportable): static
    {
        return $this->state(fn () => [
            'reportable_type' => $reportable instanceof Post ? 'post' : 'comment',
            'reportable_id' => $reportable->id,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['status' => ReportStatus::Resolved]);
    }
}
