<?php

namespace App\Http\Resources;

use App\Models\BookText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookText
 */
class BookTextResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_book_id' => $this->user_book_id,
            'source_name' => $this->source_name,
            'chapters_count' => $this->chapters_count,
            'characters_count' => $this->characters_count,
            // Where the reader is: a chapter and how far into it, from 0 to 1.
            'position' => [
                'chapter' => $this->chapter_position,
                'chapter_progress' => $this->chapter_progress,
                'updated_at' => $this->position_updated_at?->toIso8601String(),
            ],
            // How far into the whole text, from 0 to 1.
            'progress' => $this->progress,
            // The table of contents, without the text of the chapters.
            'chapters' => $this->whenLoaded('contents', fn () => $this->contents->map(fn ($chapter) => [
                'position' => $chapter->position,
                'title' => $chapter->title,
                'characters' => $chapter->characters,
            ])->all()),
        ];
    }
}
