<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Users\CreateUser;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password;

final class CreateUserCommand extends Command
{
    protected $signature = 'user:create {name : Display name} {email : Login email}';

    protected $description = 'Create the one user of this app (there is no registration page)';

    public function handle(CreateUser $action): int
    {
        try {
            $user = $action->handle(
                (string) $this->argument('name'),
                (string) $this->argument('email'),
                password(label: 'Password', required: true),
            );
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $this->components->info("Created {$user->email}.");

        return self::SUCCESS;
    }
}
