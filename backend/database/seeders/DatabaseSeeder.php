<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with local development data.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Demo Reader',
            'email' => 'demo@bookshelf.test',
        ]);
    }
}
