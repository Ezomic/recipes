<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Models\User;

final class PruneUnusedTags
{
    public function handle(User $user): void
    {
        $user->tags()->whereDoesntHave('recipes')->delete();
    }
}
