<?php

namespace Database\Factories;

use App\Models\ReaderDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReaderDocument>
 */
class ReaderDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'author' => fake()->name(),
            'path' => fn (array $attributes) => 'reader/'.$attributes['user_id'].'/'.Str::uuid().'.epub',
            'chapters' => ['OEBPS/ch1.xhtml', 'OEBPS/ch2.xhtml', 'OEBPS/ch3.xhtml'],
            'chapters_count' => fn (array $attributes) => count($attributes['chapters']),
            'position_chapter' => 0,
            'position_fraction' => 0,
        ];
    }
}
