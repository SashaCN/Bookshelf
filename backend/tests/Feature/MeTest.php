<?php

use App\Models\User;

it('requires authentication', function () {
    $this->getJson('/api/me')->assertUnauthorized();
    $this->patchJson('/api/me', ['name' => 'Hacker'])->assertUnauthorized();
});

it('returns the authenticated user', function () {
    $user = User::factory()->create(['timezone' => 'Europe/Kyiv']);

    $this->actingAs($user)->getJson('/api/me')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => 'Europe/Kyiv',
        ]]);
});

it('updates the name and time zone', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patchJson('/api/me', [
        'name' => 'New Name',
        'timezone' => 'America/New_York',
    ])->assertOk()->assertJsonPath('data.timezone', 'America/New_York');

    expect($user->fresh())
        ->name->toBe('New Name')
        ->timezone->toBe('America/New_York');
});

it('rejects an unknown time zone', function () {
    $this->actingAs(User::factory()->create())
        ->patchJson('/api/me', ['timezone' => 'Mars/Olympus'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('timezone');
});

it('does not allow changing the email through the profile endpoint', function () {
    $user = User::factory()->create(['email' => 'olena@example.com']);

    $this->actingAs($user)->patchJson('/api/me', ['email' => 'other@example.com'])->assertOk();

    expect($user->fresh()->email)->toBe('olena@example.com');
});
