<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
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
        $input = ['name' => $name, 'email' => $email, 'password' => $password];

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => ['required', 'string', Password::default()],
        ])->validate();

        $user = User::create($input);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
