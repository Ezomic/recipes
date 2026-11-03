<?php

declare(strict_types=1);

namespace App\Http\Requests\Recipes;

use App\Concerns\RecipeValidationRules;
use Illuminate\Foundation\Http\FormRequest;

final class StoreRecipeRequest extends FormRequest
{
    use RecipeValidationRules;
}
