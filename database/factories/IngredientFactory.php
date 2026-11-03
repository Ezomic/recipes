<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IngredientUnit;
use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    protected $model = Ingredient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'position' => 0,
            'quantity' => 1,
            'unit' => IngredientUnit::Gram,
            'name' => fake()->word(),
        ];
    }
}
