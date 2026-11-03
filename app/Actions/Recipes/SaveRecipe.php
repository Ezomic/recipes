<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Enums\IngredientUnit;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SaveRecipe
{
    public function __construct(private readonly PruneUnusedTags $pruneUnusedTags = new PruneUnusedTags) {}

    /**
     * @param  array<array-key, mixed>  $steps
     * @param  array<array-key, mixed>  $ingredients
     * @param  array<array-key, mixed>  $tags
     */
    public function handle(
        User $user,
        string $title,
        array $steps,
        int $preparationMinutes,
        int $servings,
        bool $houdbaar,
        array $ingredients,
        array $tags = [],
        ?Recipe $recipe = null,
    ): Recipe {
        return DB::transaction(function () use ($user, $title, $steps, $preparationMinutes, $servings, $houdbaar, $ingredients, $tags, $recipe): Recipe {
            $recipe ??= $user->recipes()->make();

            if ($recipe->exists) {
                Recipe::query()->whereKey($recipe->getKey())->lockForUpdate()->first();
            }

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

            $recipe->tags()->sync($this->resolveTags($user, $tags));
            $recipe->unsetRelation('tags');
            $this->pruneUnusedTags->handle($user);

            return $recipe;
        });
    }

    /**
     * @param  array<array-key, mixed>  $tags
     * @return list<int>
     */
    private function resolveTags(User $user, array $tags): array
    {
        $names = [];

        foreach ($tags as $tag) {
            $name = is_string($tag) ? Tag::normalise($tag) : '';

            if ($name !== '') {
                $names[] = $name;
            }
        }

        $names = array_values(array_unique($names));

        $existing = $user->tags()->whereIn('name', $names)->get()->keyBy('name');
        $ids = [];

        foreach ($names as $name) {
            $ids[] = ($existing[$name] ?? $user->tags()->create(['name' => $name]))->id;
        }

        return $ids;
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
