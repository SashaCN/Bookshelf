<?php

namespace Database\Factories;

use App\Models\ReadingLog;
use App\Models\UserBook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingLog>
 */
class ReadingLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_book_id' => UserBook::factory()->reading(),
            // The log belongs to the same reader as its entry.
            'user_id' => fn (array $attributes) => UserBook::query()->findOrFail($attributes['user_book_id'])->user_id,
            'from_page' => 0,
            'to_page' => 20,
            'logged_on' => now()->toDateString(),
        ];
    }
}
