<?php

namespace Database\Factories;

use App\Enums\PostType;
use App\Models\Book;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * A quote post by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory()->withAuthors(),
            'type' => PostType::Quote,
            'content' => fake()->sentence(12),
            'comment' => null,
            'rating' => null,
            'page' => null,
            'hidden_at' => null,
        ];
    }

    public function review(?int $rating = null): static
    {
        return $this->state(fn () => [
            'type' => PostType::Review,
            'rating' => $rating ?? fake()->numberBetween(1, 5),
            'content' => fake()->sentence(20),
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['hidden_at' => now()]);
    }
}
