<?php

use Illuminate\Support\Facades\DB;

// A temporary table (creating one does not commit the test's transaction) with the shape the migration works on.
beforeEach(function () {
    DB::statement('CREATE TEMPORARY TABLE handle_backfill (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, handle VARCHAR(30) NULL)');
});

afterEach(function () {
    DB::statement('DROP TEMPORARY TABLE IF EXISTS handle_backfill');
});

function backfill(array $names): array
{
    foreach ($names as $name) {
        DB::table('handle_backfill')->insert(['name' => $name]);
    }

    (include database_path('migrations/2026_10_09_100000_add_community_columns_to_users_table.php'))->backfillHandles('handle_backfill');

    return DB::table('handle_backfill')->orderBy('id')->pluck('handle')->all();
}

it('gives every existing reader a handle made of the name', function () {
    expect(backfill(['Olena Kovalenko', 'Олена Шевченко', 'Taras']))->toBe(['olena_kovalenko', 'olena_shevchenko', 'taras']);
});

it('numbers readers with the same name in the order they signed up', function () {
    expect(backfill(['Olena', 'Olena', 'olena', 'Олена']))->toBe(['olena', 'olena2', 'olena3', 'olena4']);
});

it('falls back to a plain word for names with nothing usable in them', function () {
    expect(backfill(['🙂', 'Я', '!!', 'Ab']))->toBe(['reader', 'reader2', 'reader3', 'reader4']);
});

it('makes only valid handles, unique, of any name', function () {
    $handles = backfill([str_repeat('Oleksandrivna ', 8), str_repeat('Oleksandrivna ', 8), 'Zoë Çelik', '山田太郎', '12345', '___a___']);

    expect($handles)->each->toMatch('/^[a-z0-9_]{3,30}$/')
        ->and(array_unique($handles))->toHaveCount(count($handles));
});

it('gives a handle to every reader', function () {
    expect(backfill(['A', 'B', 'C']))->each->not->toBeNull();
});
