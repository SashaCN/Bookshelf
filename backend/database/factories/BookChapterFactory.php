<?php

namespace Database\Factories;

use App\Models\BookChapter;
use App\Models\BookText;
use App\Services\Epub\Text;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookChapter>
 */
class BookChapterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $html = '<p>'.fake()->sentence(10).'</p>';

        return [
            'book_text_id' => BookText::factory(),
            'position' => 0,
            'title' => fake()->words(3, true),
            'content' => $html,
            'characters' => Text::length(Text::ofHtml($html)),
        ];
    }
}
