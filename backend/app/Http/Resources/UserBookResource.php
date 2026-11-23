<?php

namespace App\Http\Resources;

use App\Enums\BookStatus;
use App\Models\UserBook;
use App\Models\UserBookRead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserBook
 */
class UserBookResource extends JsonResource
{
    /** Whether the archived passes are listed in full (the single entry), not only counted (the library list). */
    private bool $withReads = false;

    public function withReads(): static
    {
        $this->withReads = true;

        return $this;
    }

    /**
     * Archived passes plus the current one when it is finished; uses the preloaded count or relation when there is one.
     */
    private function timesRead(): int
    {
        $archived = $this->reads_count ?? $this->reads()->count();

        return $archived + ($this->status === BookStatus::Finished ? 1 : 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'status' => $this->status,
            // The statuses this entry may move to, so clients never duplicate the transition rules.
            'allowed_statuses' => array_map(fn (BookStatus $status) => $status->value, $this->status->allowedTransitions()),
            'current_page' => $this->current_page,
            'total_pages' => $this->total_pages,
            'progress_percent' => $this->progressPercent(),
            // Whether the reader uploaded the text of the book, and how far into it they are (from 0 to 1).
            'has_text' => $this->bookText !== null,
            'text_progress' => $this->bookText?->progress,
            'rating' => $this->rating,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'times_read' => $this->timesRead(),
            'created_at' => $this->created_at?->toIso8601String(),
            'book' => new BookResource($this->whenLoaded('book')),
        ];

        if ($this->withReads) {
            $data['reads'] = $this->reads->map(fn (UserBookRead $read) => [
                'started_at' => $read->started_at?->toIso8601String(),
                'finished_at' => $read->finished_at->toIso8601String(),
            ])->all();
        }

        return $data;
    }
}
