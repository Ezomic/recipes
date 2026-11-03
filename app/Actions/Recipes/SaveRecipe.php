<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Str;

final class SaveRecipe
{
    /**
     * @param  array<array-key, mixed>  $steps
     */
    public function handle(
        User $user,
        string $title,
        array $steps,
        int $preparationMinutes,
        int $servings,
        bool $houdbaar,
        ?Recipe $recipe = null,
    ): Recipe {
        $recipe ??= $user->recipes()->make();

        $recipe->forceFill([
            'title' => Str::trim($title),
            'steps' => $this->cleanSteps($steps),
            'preparation_minutes' => $preparationMinutes,
            'servings' => $servings,
            'houdbaar' => $houdbaar,
        ])->save();

        return $recipe;
    }

    /**
     * @param  array<array-key, mixed>  $steps
     * @return list<string>
     */
    private function cleanSteps(array $steps): array
    {
        $cleaned = [];

        foreach ($steps as $step) {
            $step = is_string($step) ? Str::trim($step) : '';

            if ($step !== '') {
                $cleaned[] = $step;
            }
        }

        return $cleaned;
    }
}
