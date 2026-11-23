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
    private bool $withToc = false;

    /**
     * The table of contents is only sent with a single book, not in the list.
     */
    public function withToc(): static
    {
        $this->withToc = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // The list loads this for every document at once; a single document gets it here.
        $this->resource->loadMissing('userBook.book');

        /** @var array<string, mixed> $data */
        $data = [
            'id' => $this->id,
            'user_book_id' => $this->user_book_id,
            'user_book' => $this->userBook === null ? null : [
                'id' => $this->userBook->id,
                'title' => $this->userBook->book->title,
            ],
            'title' => $this->title,
            'author' => $this->author,
            'chapters_count' => $this->chapters_count,
            'position' => [
                'chapter' => $this->position_chapter,
                'fraction' => $this->position_fraction,
            ],
            'progress' => $this->progress(),
            'last_read_at' => $this->last_read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if ($this->withToc) {
            $data['toc'] = $this->toc();
        }

        return $data;
    }
}
