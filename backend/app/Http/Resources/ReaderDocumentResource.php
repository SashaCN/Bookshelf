<?php

namespace App\Http\Resources;

use App\Models\ReaderDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReaderDocument
 */
class ReaderDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'chapters_count' => $this->chapters_count,
            'position' => [
                'chapter' => $this->position_chapter,
                'fraction' => $this->position_fraction,
            ],
            'progress' => $this->progress(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
