<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Auth\IssueLoginLink;
use App\Models\User;
use Illuminate\Console\Command;

final class LoginLinkCommand extends Command
{
    protected $signature = 'user:login-link';

    protected $description = 'Print a one-time link, valid for 15 minutes, that signs the one user in';

    public function handle(IssueLoginLink $action): int
    {
        $user = User::query()->first();

        if (! $user instanceof User) {
            $this->components->error('No user exists yet. Run user:create first.');

            return self::FAILURE;
        }

        $this->line($action->handle($user));

        return self::SUCCESS;
    }
}
