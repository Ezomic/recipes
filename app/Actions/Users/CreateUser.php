<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class CreateUser
{
    use ProfileValidationRules;

    /**
     * @throws ValidationException
     */
    public function handle(string $name, string $email, string $password): User
    {
        $input = ['name' => trim($name), 'email' => Str::lower(trim($email)), 'password' => $password];

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => ['required', 'string', Password::default()],
        ])->validate();

        return DB::transaction(function () use ($input): User {
            if (User::query()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'A user already exists. This app has exactly one login.',
                ]);
            }

            $user = new User;
            $user->forceFill([...$input, 'email_verified_at' => now()])->save();

            return $user;
        });
    }
}
