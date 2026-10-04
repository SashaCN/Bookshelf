<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_request-info', fn (Request $request) => [
        'ip' => $request->ip(),
        'secure' => $request->isSecure(),
        'host' => $request->getHost(),
    ]);
});

it('uses the client address and scheme forwarded by the reverse proxy', function () {
    $this->getJson('/_request-info', [
        'X-Forwarded-For' => '203.0.113.9',
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'bookshelf.example.com',
    ])->assertOk()->assertExactJson([
        'ip' => '203.0.113.9',
        'secure' => true,
        'host' => 'bookshelf.example.com',
    ]);
});

it('falls back to the direct connection when nothing is forwarded', function () {
    $this->getJson('/_request-info')->assertOk()->assertJsonPath('secure', false);
});

it('throttles logins per client, not per proxy', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'wrong-password'], ['X-Forwarded-For' => '203.0.113.1'])
            ->assertUnprocessable();
    }

    // The first client is locked out...
    $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'wrong-password'], ['X-Forwarded-For' => '203.0.113.1'])
        ->assertTooManyRequests();

    // ...but a different client behind the same proxy is not.
    $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'wrong-password'], ['X-Forwarded-For' => '203.0.113.2'])
        ->assertUnprocessable();
});
