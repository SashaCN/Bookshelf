<?php

namespace App\Http\Resources;

use App\Models\BookChapter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One chapter with its text, and the quotes selected from it, which are to be highlighted.
 *
 * @mixin BookChapter
 */
class BookChapterResource extends JsonResource
{
    /**
     * @param  list<array{id: int, start: int, end: int, content: string, note: string|null}>  $highlights
     */
    public function __construct($resource, private readonly array $highlights)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'position' => $this->position,
            'title' => $this->title,
            // Sanitized when the book was imported: elements from a short list, no attributes, no scripts.
            'html' => $this->content,
            'characters' => $this->characters,
            'highlights' => $this->highlights,
        ];
    }
}
