<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Recipes\PruneUnusedTags;
use App\Actions\Recipes\SaveRecipe;
use App\Actions\Recipes\SearchRecipes;
use App\Http\Requests\Recipes\IndexRecipesRequest;
use App\Http\Requests\Recipes\StoreRecipeRequest;
use App\Http\Requests\Recipes\UpdateRecipeRequest;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RecipeController extends Controller
{
    public function index(IndexRecipesRequest $request, SearchRecipes $search): Response|RedirectResponse
    {
        $user = $this->user($request);
        $filters = $request->filters();
        $recipes = $search->handle($user, $filters);

        if ($recipes->currentPage() > $recipes->lastPage()) {
            return redirect($request->fullUrlWithQuery(['page' => $recipes->lastPage()]));
        }

        return Inertia::render('recipes/Index', [
            'recipes' => $recipes,
            'filters' => $filters->toArray(),
            'knownTags' => $this->knownTags($user),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('recipes/Create', ['knownTags' => $this->knownTags($this->user($request))]);
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
            tags: $request->array('tags'),
        );

        return to_route('recipes.show', $recipe);
    }

    public function show(Recipe $recipe): Response
    {
        $this->authorize('view', $recipe);

        return Inertia::render('recipes/Show', ['recipe' => $recipe->load('ingredients', 'tags')]);
    }

    public function edit(Request $request, Recipe $recipe): Response
    {
        $this->authorize('update', $recipe);

        return Inertia::render('recipes/Edit', [
            'recipe' => $recipe->load('ingredients', 'tags'),
            'knownTags' => $this->knownTags($this->user($request)),
        ]);
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
            tags: $request->array('tags'),
            recipe: $recipe,
        );

        return to_route('recipes.show', $recipe);
    }

    public function destroy(Request $request, Recipe $recipe, PruneUnusedTags $pruneUnusedTags): RedirectResponse
    {
        $this->authorize('delete', $recipe);

        $recipe->delete();
        $pruneUnusedTags->handle($this->user($request));

        return to_route('recipes.index');
    }

    /**
     * @return list<string>
     */
    private function knownTags(User $user): array
    {
        return array_values($user->tags()->orderBy('name')->get()->map(fn (Tag $tag): string => $tag->name)->all());
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
