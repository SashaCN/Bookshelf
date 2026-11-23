<?php

namespace App\Http\Resources;

use App\Models\ReaderBookmark;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReaderBookmark
 */
class ReaderBookmarkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $title = $this->readerDocument->chapter_titles[$this->chapter] ?? null;

        return [
            'id' => $this->id,
            'chapter' => $this->chapter,
            'chapter_title' => is_string($title) && $title !== '' ? $title : null,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
