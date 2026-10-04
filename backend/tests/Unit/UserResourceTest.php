<?php

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

it('exposes only the public profile fields', function () {
    $user = new User([
        'name' => 'Olena',
        'email' => 'olena@example.com',
        'password' => 'secret-password',
        'timezone' => 'Europe/Kyiv',
    ]);
    $user->id = 7;

    $payload = (new UserResource($user))->toArray(Request::create('/'));

    expect($payload)->toBe([
        'id' => 7,
        'name' => 'Olena',
        'email' => 'olena@example.com',
        'timezone' => 'Europe/Kyiv',
    ]);
});
