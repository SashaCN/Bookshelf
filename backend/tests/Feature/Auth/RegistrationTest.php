<?php

use App\Models\User;

it('registers a user, logs them in and stores the time zone', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Olena',
        'email' => 'Olena@Example.com',
        'password' => 'secret-password',
        'timezone' => 'Europe/Warsaw',
    ]);

    $response->assertCreated();

    $user = User::firstWhere('email', 'olena@example.com');

    expect($user)->not->toBeNull()
        ->and($user->timezone)->toBe('Europe/Warsaw');

    $this->assertAuthenticatedAs($user);
});

it('falls back to the default time zone', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Olena',
        'email' => 'olena@example.com',
        'password' => 'secret-password',
    ])->assertCreated();

    expect(User::first()->timezone)->toBe(User::DEFAULT_TIMEZONE);
});

it('rejects invalid registration data', function (array $payload, string $field) {
    $this->postJson('/api/auth/register', $payload + [
        'name' => 'Olena',
        'email' => 'olena@example.com',
        'password' => 'secret-password',
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'short password' => [['password' => 'short'], 'password'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'missing name' => [['name' => ''], 'name'],
    'unknown time zone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
]);

it('limits the name to 100 characters', function () {
    $payload = ['email' => 'long@example.com', 'password' => 'secret-password'];

    $this->postJson('/api/auth/register', $payload + ['name' => str_repeat('я', 101)])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson('/api/auth/register', $payload + ['name' => str_repeat('я', 100)])->assertCreated();
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'olena@example.com']);

    $this->postJson('/api/auth/register', [
        'name' => 'Another Olena',
        'email' => 'olena@example.com',
        'password' => 'secret-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('accepts a legacy time zone name that some browsers still report and stores the canonical one', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Olena',
        'email' => 'olena@example.com',
        'password' => 'secret-password',
        'timezone' => 'Europe/Kiev',
    ])->assertCreated();

    expect(User::first()->timezone)->toBe('Europe/Kyiv');
});

it('throttles registrations from one address', function () {
    foreach (range(1, 30) as $attempt) {
        $this->postJson('/api/auth/register', [
            'name' => 'Olena',
            'email' => "olena{$attempt}@example.com",
            'password' => 'secret-password',
        ])->assertCreated();

        // Registering signs the new user in, and Fortify turns signed-in callers away.
        $this->flushSession();
        $this->actAsGuest();
    }

    $this->postJson('/api/auth/register', [
        'name' => 'Olena',
        'email' => 'one-too-many@example.com',
        'password' => 'secret-password',
    ])->assertTooManyRequests();
});
