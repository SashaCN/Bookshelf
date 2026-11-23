<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\QuoteComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteComment>
 */
class QuoteCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(8),
        ];
    }
}
