<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Enums\IngredientUnit;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SaveRecipe
{
    /**
     * @param  array<array-key, mixed>  $steps
     * @param  array<array-key, mixed>  $ingredients
     */
    public function handle(
        User $user,
        string $title,
        array $steps,
        int $preparationMinutes,
        int $servings,
        bool $houdbaar,
        array $ingredients,
        ?Recipe $recipe = null,
    ): Recipe {
        return DB::transaction(function () use ($user, $title, $steps, $preparationMinutes, $servings, $houdbaar, $ingredients, $recipe): Recipe {
            $recipe ??= $user->recipes()->make();

            $recipe->forceFill([
                'title' => Str::trim($title),
                'steps' => $this->cleanSteps($steps),
                'preparation_minutes' => $preparationMinutes,
                'servings' => $servings,
                'houdbaar' => $houdbaar,
            ])->save();

            $recipe->ingredients()->delete();
            $recipe->ingredients()->createMany($this->cleanIngredients($ingredients));
            $recipe->unsetRelation('ingredients');

            return $recipe;
        });
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

    /**
     * @param  array<array-key, mixed>  $ingredients
     * @return list<array{position: int, quantity: float|string|null, unit: IngredientUnit|null, name: string}>
     */
    private function cleanIngredients(array $ingredients): array
    {
        $cleaned = [];

        foreach ($ingredients as $row) {
            $name = is_array($row) && is_string($row['name'] ?? null) ? Str::trim($row['name']) : '';

            if ($name === '') {
                continue;
            }

            $quantity = $row['quantity'] ?? null;

            $cleaned[] = [
                'position' => count($cleaned),
                'quantity' => is_numeric($quantity) ? $quantity : null,
                'unit' => is_string($row['unit'] ?? null) ? IngredientUnit::tryFrom($row['unit']) : null,
                'name' => $name,
            ];
        }

        return $cleaned;
    }
}
