<?php

namespace App\Http\Resources;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Book
 */
class BookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'authors' => $this->authors->pluck('name')->all(),
            'isbn_13' => $this->isbn_13,
            'cover_url' => $this->cover_url,
            'page_count' => $this->page_count,
            'published_year' => $this->published_year,
            'subjects' => $this->subjects ?? [],
            'source' => $this->source,
        ];
    }
}
