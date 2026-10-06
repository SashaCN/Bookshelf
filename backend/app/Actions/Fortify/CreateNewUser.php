<?php

namespace App\Actions\Fortify;

use App\Actions\Social\GenerateHandle;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Throwable;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private readonly GenerateHandle $handles) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'timezone' => ['nullable', 'timezone:all'],
        ])->validate();

        // The handle comes from the name. Two readers with the same name registering at the very same moment
        // may be given the same one; the unique index stops the second, who simply gets the next free handle.
        return retry(
            3,
            fn () => User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'handle' => $this->handles->handle($input['name']),
                'password' => Hash::make($input['password']),
                'timezone' => $input['timezone'] ?? User::DEFAULT_TIMEZONE,
            ]),
            0,
            fn (Throwable $e) => $e instanceof UniqueConstraintViolationException && str_contains($e->getMessage(), 'handle'),
        );
    }
}
