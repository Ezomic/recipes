<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use Illuminate\Support\Facades\Storage;

final class DeleteRecipePhoto
{
    public function handle(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }
    }
}
