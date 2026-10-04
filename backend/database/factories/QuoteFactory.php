<?php

namespace Database\Factories;

use App\Enums\QuoteType;
use App\Models\Quote;
use App\Models\UserBook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_book_id' => UserBook::factory(),
            // The quote belongs to the same reader as its entry.
            'user_id' => fn (array $attributes) => UserBook::query()->findOrFail($attributes['user_book_id'])->user_id,
            'type' => QuoteType::Quote,
            'content' => fake()->sentence(12),
            'note' => null,
            'page' => null,
            'is_favorite' => false,
        ];
    }

    public function favorite(): static
    {
        return $this->state(fn () => ['is_favorite' => true]);
    }

    public function insight(): static
    {
        return $this->state(fn () => ['type' => QuoteType::Insight]);
    }
}
