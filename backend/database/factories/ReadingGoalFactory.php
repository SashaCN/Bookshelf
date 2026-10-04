<?php

namespace Database\Factories;

use App\Models\ReadingGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingGoal>
 */
class ReadingGoalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'year' => (int) now()->format('Y'),
            'target_books' => 12,
        ];
    }
}
