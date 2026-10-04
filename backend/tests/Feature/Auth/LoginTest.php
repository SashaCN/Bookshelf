<?php

use App\Models\User;

it('logs in with valid credentials', function () {
    $user = User::factory()->create(['email' => 'olena@example.com']);

    $this->postJson('/api/auth/login', [
        'email' => 'olena@example.com',
        'password' => 'password',
    ])->assertOk();

    $this->assertAuthenticatedAs($user);
});

it('rejects wrong credentials', function () {
    User::factory()->create(['email' => 'olena@example.com']);

    $this->postJson('/api/auth/login', [
        'email' => 'olena@example.com',
        'password' => 'wrong-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('logs the user out', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson('/api/auth/logout')->assertNoContent();

    $this->assertGuest();
});

it('throttles repeated failed logins', function () {
    User::factory()->create(['email' => 'olena@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/auth/login', [
            'email' => 'olena@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/auth/login', [
        'email' => 'olena@example.com',
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
});
