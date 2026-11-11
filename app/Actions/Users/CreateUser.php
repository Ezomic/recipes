<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateUser
{
    use ProfileValidationRules;

    /**
     * The user signs in with an emailed code, so the password column only holds a random
     * hash that nobody knows.
     *
     * @throws ValidationException
     */
    public function handle(string $name, string $email): User
    {
        $input = ['name' => trim($name), 'email' => Str::lower(trim($email))];

        Validator::make($input, $this->profileRules())->validate();

        return DB::transaction(function () use ($input): User {
            if (User::query()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Er bestaat al een gebruiker. Deze app heeft precies één login.',
                ]);
            }

            $user = new User;
            $user->forceFill([
                ...$input,
                'password' => Str::random(64),
                'email_verified_at' => now(),
            ])->save();

            return $user;
        });
    }
}
