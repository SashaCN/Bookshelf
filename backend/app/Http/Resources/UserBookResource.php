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
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            // The statuses this entry may move to, so clients never duplicate the transition rules.
            'allowed_statuses' => array_map(fn (BookStatus $status) => $status->value, $this->status->allowedTransitions()),
            'current_page' => $this->current_page,
            'total_pages' => $this->total_pages,
            'progress_percent' => $this->progressPercent(),
            'rating' => $this->rating,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'book' => new BookResource($this->whenLoaded('book')),
        ];
    }
}
