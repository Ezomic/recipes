<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Recipes\SaveRecipe;
use App\Http\Requests\Recipes\StoreRecipeRequest;
use App\Http\Requests\Recipes\UpdateRecipeRequest;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

final class RecipeController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('recipes/Index', [
            'recipes' => $this->user($request)->recipes()
                ->latest()
                ->latest('id')
                ->paginate(Config::integer('pagination.per_page'))
                ->withQueryString(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('recipes/Create');
    }

    public function store(StoreRecipeRequest $request, SaveRecipe $action): RedirectResponse
    {
        $recipe = $action->handle(
            user: $this->user($request),
            title: $request->string('title')->toString(),
            steps: $request->array('steps'),
            preparationMinutes: $request->integer('preparation_minutes'),
            servings: $request->integer('servings'),
            houdbaar: $request->boolean('houdbaar'),
            ingredients: $request->array('ingredients'),
        );

        return to_route('recipes.show', $recipe);
    }

    public function show(Recipe $recipe): Response
    {
        $this->authorize('view', $recipe);

        return Inertia::render('recipes/Show', ['recipe' => $recipe->load('ingredients')]);
    }

    public function edit(Recipe $recipe): Response
    {
        $this->authorize('update', $recipe);

        return Inertia::render('recipes/Edit', ['recipe' => $recipe->load('ingredients')]);
    }

    public function update(UpdateRecipeRequest $request, Recipe $recipe, SaveRecipe $action): RedirectResponse
    {
        $action->handle(
            user: $this->user($request),
            title: $request->string('title')->toString(),
            steps: $request->array('steps'),
            preparationMinutes: $request->integer('preparation_minutes'),
            servings: $request->integer('servings'),
            houdbaar: $request->boolean('houdbaar'),
            ingredients: $request->array('ingredients'),
            recipe: $recipe,
        );

        return to_route('recipes.show', $recipe);
    }

    public function destroy(Recipe $recipe): RedirectResponse
    {
        $this->authorize('delete', $recipe);

        $recipe->delete();

        return to_route('recipes.index');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
