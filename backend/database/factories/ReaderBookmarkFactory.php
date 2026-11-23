<?php

namespace Database\Factories;

use App\Models\ReaderBookmark;
use App\Models\ReaderDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReaderBookmark>
 */
class ReaderBookmarkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reader_document_id' => ReaderDocument::factory(),
            'chapter' => 0,
            'note' => null,
        ];
    }
}
