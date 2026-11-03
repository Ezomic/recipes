<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    protected $model = Recipe::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'steps' => [fake()->sentence(), fake()->sentence()],
            'preparation_minutes' => fake()->numberBetween(5, 120),
            'servings' => fake()->numberBetween(1, 8),
            'houdbaar' => fake()->boolean(),
            'photo_path' => null,
        ];
    }
}
