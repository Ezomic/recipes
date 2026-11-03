<?php

declare(strict_types=1);

namespace App\Http\Requests\Recipes;

use App\Concerns\RecipeValidationRules;
use App\Models\Recipe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateRecipeRequest extends FormRequest
{
    use RecipeValidationRules;

    public function authorize(): bool
    {
        $recipe = $this->route('recipe');

        return $recipe instanceof Recipe && Gate::authorize('update', $recipe)->allowed();
    }
}
