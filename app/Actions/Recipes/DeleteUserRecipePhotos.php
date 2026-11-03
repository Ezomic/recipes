<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

final class DeleteUserRecipePhotos
{
    public function handle(User $user): void
    {
        Storage::disk('local')->deleteDirectory(StoreRecipePhoto::directory($user));
    }
}
