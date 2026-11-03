<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class RecipePolicy
{
    public function view(User $user, Recipe $recipe): Response
    {
        return $this->owns($user, $recipe);
    }

    public function update(User $user, Recipe $recipe): Response
    {
        return $this->owns($user, $recipe);
    }

    public function delete(User $user, Recipe $recipe): Response
    {
        return $this->owns($user, $recipe);
    }

    private function owns(User $user, Recipe $recipe): Response
    {
        return $user->id === $recipe->user_id ? Response::allow() : Response::denyAsNotFound();
    }
}
