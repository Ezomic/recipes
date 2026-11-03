<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\User;
use App\Support\RecipeFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;

final class SearchRecipes
{
    /**
     * @return LengthAwarePaginator<int, Recipe>
     */
    public function handle(User $user, RecipeFilters $filters): LengthAwarePaginator
    {
        $query = $user->recipes()->with('tags')->latest()->latest('id');

        if ($filters->search !== null) {
            // The query builder has no ESCAPE option for LIKE, so user wildcards can only be neutralised through a bound escape character.
            $pattern = '%'.addcslashes($filters->search, '\\%_').'%';

            $query->where(fn (Builder $match) => $match
                ->whereRaw('recipes.title like ? escape ?', [$pattern, '\\'])
                ->orWhereHas('ingredients', fn (Builder $ingredient) => $ingredient
                    ->whereRaw('ingredients.name like ? escape ?', [$pattern, '\\'])));
        }

        if ($filters->tags !== []) {
            $query->whereHas('tags', fn (Builder $tag) => $tag->whereIn('tags.name', $filters->tags), '=', count($filters->tags));
        }

        if ($filters->maxMinutes !== null) {
            $query->where('preparation_minutes', '<=', $filters->maxMinutes);
        }

        if ($filters->houdbaar) {
            $query->where('houdbaar', true);
        }

        return $query->paginate(Config::integer('pagination.per_page'), page: $filters->page)->withQueryString();
    }
}
