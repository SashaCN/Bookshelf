<?php

namespace Database\Factories;

use App\Models\BookChapter;
use App\Models\BookText;
use App\Models\UserBook;
use App\Services\Epub\Text;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookText>
 */
class BookTextFactory extends Factory
{
    /**
     * A text without chapters; give it some with withChapters().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_book_id' => UserBook::factory(),
            // The text belongs to the same reader as its entry.
            'user_id' => fn (array $attributes) => UserBook::query()->findOrFail($attributes['user_book_id'])->user_id,
            'source_name' => 'book.epub',
            'chapters_count' => 0,
            'characters_count' => 0,
            'chapter_position' => 0,
            'chapter_progress' => 0,
            'progress' => 0,
            'position_updated_at' => null,
        ];
    }

    /**
     * Gives the text chapters, and the counts that go with them. A chapter is its sanitized HTML, or a title and that.
     *
     * @param  list<string|array{0: string|null, 1: string}>  $chapters
     */
    public function withChapters(array $chapters): static
    {
        return $this->afterCreating(function (BookText $text) use ($chapters) {
            $characters = 0;

            foreach ($chapters as $position => $chapter) {
                [$title, $html] = is_array($chapter) ? $chapter : [null, $chapter];
                $length = Text::length(Text::ofHtml($html));

                BookChapter::query()->create([
                    'book_text_id' => $text->getKey(),
                    'position' => $position,
                    'title' => $title,
                    'content' => $html,
                    'characters' => $length,
                ]);

                $characters += $length;
            }

            $text->update(['chapters_count' => count($chapters), 'characters_count' => $characters]);
        });
    }

    /**
     * Three short chapters of different length, 30 + 6 + 27 characters (the last one has an emoji, which is two).
     */
    public function withSampleChapters(): static
    {
        return $this->withChapters([
            ['One', '<p>The first chapter has a start.</p>'],
            ['Two', '<p>Short.</p>'],
            [null, '<h2>Three</h2><p>The third one: 😀 end.</p>'],
        ]);
    }

    public function at(int $chapter, float $chapterProgress, float $progress): static
    {
        return $this->state(fn () => [
            'chapter_position' => $chapter,
            'chapter_progress' => $chapterProgress,
            'progress' => $progress,
            'position_updated_at' => now(),
        ]);
    }
}
