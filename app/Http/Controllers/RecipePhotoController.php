<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Recipe;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RecipePhotoController extends Controller
{
    public function __invoke(Recipe $recipe): StreamedResponse
    {
        $this->authorize('view', $recipe);

        $path = $recipe->photo_path;
        $disk = Storage::disk('local');

        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
