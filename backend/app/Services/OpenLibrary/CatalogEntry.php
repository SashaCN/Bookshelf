<?php

namespace App\Services\OpenLibrary;

/**
 * A book as described by the external catalog, already normalized for our own use.
 */
final readonly class CatalogEntry
{
    /**
     * @param  list<string>  $authors
     * @param  list<string>  $subjects
     */
    public function __construct(
        public string $workKey,
        public string $title,
        public ?string $subtitle,
        public array $authors,
        public ?string $coverUrl,
        public ?int $pageCount,
        public ?int $publishedYear,
        public array $subjects,
        public ?string $isbn13,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'work_key' => $this->workKey,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'authors' => $this->authors,
            'cover_url' => $this->coverUrl,
            'page_count' => $this->pageCount,
            'published_year' => $this->publishedYear,
            'subjects' => $this->subjects,
            'isbn_13' => $this->isbn13,
        ];
    }
}
