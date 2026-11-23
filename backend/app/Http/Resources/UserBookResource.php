<?php

namespace App\Http\Resources;

use App\Enums\BookStatus;
use App\Models\UserBook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserBook
 */
class UserBookResource extends JsonResource
{
    private bool $withReaderDocument = false;

    /**
     * The linked EPUB is only looked up for a single entry, not in the list.
     */
    public function withReaderDocument(): static
    {
        $this->withReaderDocument = true;

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
        /** @var array<string, mixed> $data */
        $data = [
            'id' => $this->id,
            'status' => $this->status,
            // The statuses this entry may move to, so clients never duplicate the transition rules.
            'allowed_statuses' => array_map(fn (BookStatus $status) => $status->value, $this->status->allowedTransitions()),
            'current_page' => $this->current_page,
            'total_pages' => $this->total_pages,
            'progress_percent' => $this->progressPercent(),
            'rating' => $this->rating,
            'review' => $this->review,
            'review_visibility' => $this->review_visibility,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'times_read' => $this->timesRead(),
            'created_at' => $this->created_at?->toIso8601String(),
            'book' => new BookResource($this->whenLoaded('book')),
        ];

        if ($this->withReaderDocument) {
            $data['reader_document_id'] = $this->readerDocument?->id;
            $data['reads'] = $this->reads->map(fn ($read) => [
                'started_at' => $read->started_at?->toIso8601String(),
                'finished_at' => $read->finished_at->toIso8601String(),
            ])->all();
        }

        return $data;
    }
}
