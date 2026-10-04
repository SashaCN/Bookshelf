<?php

namespace Database\Factories;

use App\Enums\BookSource;
use App\Models\Author;
use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'subtitle' => null,
            'isbn_13' => null,
            'openlibrary_work_key' => '/works/OL'.fake()->unique()->numberBetween(1000, 9999999).'W',
            'cover_url' => null,
            'page_count' => 320,
            'published_year' => 2016,
            'subjects' => ['Habit', 'Psychology'],
            'source' => BookSource::OpenLibrary,
            'created_by' => null,
        ];
    }

    public function manual(?User $creator = null): static
    {
        return $this->state(fn () => [
            'openlibrary_work_key' => null,
            'source' => BookSource::Manual,
            'created_by' => $creator->id ?? User::factory(),
        ]);
    }

    public function withAuthors(int $count = 1): static
    {
        return $this->afterCreating(function (Book $book) use ($count) {
            $authors = Author::factory()->count($count)->create();

            foreach ($authors as $position => $author) {
                $book->authors()->attach($author->id, ['position' => $position]);
            }
        });
    }
}
