<?php

use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Fortify (headless)
|--------------------------------------------------------------------------
|
| Bookshelf uses Fortify only as an authentication backend for the Vue SPA:
| there are no server-rendered views, and every route lives under /api/auth.
|
*/

return [

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    'home' => '/',

    'prefix' => 'api/auth',

    'domain' => null,

    'middleware' => ['web'],

    'limiters' => [
        'login' => 'login',
    ],

    'views' => false,

    'features' => [
        Features::registration(),
    ],
];
