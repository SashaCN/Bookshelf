<?php

namespace Database\Factories;

use App\Enums\BookStatus;
use App\Models\Book;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBook>
 */
class UserBookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory()->withAuthors(),
            'status' => BookStatus::Want,
            'current_page' => 0,
            'total_pages' => 320,
            'rating' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function reading(int $currentPage = 100): static
    {
        return $this->state(fn () => [
            'status' => BookStatus::Reading,
            'current_page' => $currentPage,
            'started_at' => now()->subDays(3),
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookStatus::Finished,
            'current_page' => $attributes['total_pages'] ?? 320,
            'started_at' => now()->subDays(10),
            'finished_at' => now()->subDay(),
        ]);
    }

    public function abandoned(int $currentPage = 40): static
    {
        return $this->state(fn () => [
            'status' => BookStatus::Abandoned,
            'current_page' => $currentPage,
            'started_at' => now()->subDays(20),
        ]);
    }
}
