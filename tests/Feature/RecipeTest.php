<?php

declare(strict_types=1);

use App\Actions\Recipes\SaveRecipe;
use App\Models\Recipe;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * @return array<string, mixed>
 */
function validRecipe(array $overrides = []): array
{
    return array_merge([
        'title' => 'Pannenkoeken',
        'steps' => ['Meng het beslag', 'Bak de pannenkoeken'],
        'preparation_minutes' => 30,
        'servings' => 4,
        'houdbaar' => false,
    ], $overrides);
}

dataset('guest routes', [
    'index' => ['get', '/recepten'],
    'create' => ['get', '/recepten/create'],
    'store' => ['post', '/recepten'],
    'show' => ['get', '/recepten/1'],
    'edit' => ['get', '/recepten/1/edit'],
    'update' => ['put', '/recepten/1'],
    'destroy' => ['delete', '/recepten/1'],
]);

it('sends a guest to the login page', function (string $method, string $uri) {
    $this->{$method}($uri)->assertRedirect(route('login'));
})->with('guest routes');

it('does not create anything for a guest', function () {
    $this->post('/recepten', validRecipe());

    assertDatabaseCount('recipes', 0);
});

// Index

it('lists only the current user’s recipes, newest first', function () {
    $user = User::factory()->create();
    $older = Recipe::factory()->for($user)->create(['title' => 'Oud', 'created_at' => now()->subDay()]);
    $newer = Recipe::factory()->for($user)->create(['title' => 'Nieuw', 'created_at' => now()]);
    Recipe::factory()->create(['title' => 'Van een ander']);

    actingAs($user)->get(route('recipes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('recipes/Index')
            ->has('recipes.data', 2)
            ->where('recipes.data.0.id', $newer->id)
            ->where('recipes.data.1.id', $older->id)
            ->missing('recipes.data.0.user_id'));
});

it('shows an empty list to a user without recipes', function () {
    Recipe::factory()->create();

    actingAs(User::factory()->create())->get(route('recipes.index'))
        ->assertInertia(fn (Assert $page) => $page->has('recipes.data', 0)->where('recipes.total', 0));
});

it('paginates the recipe list at the configured page size', function () {
    config()->set('pagination.per_page', 5);
    $user = User::factory()->create();
    Recipe::factory()->count(12)->for($user)->create();

    actingAs($user)->get(route('recipes.index', ['page' => 3]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recipes.data', 2)
            ->where('recipes.total', 12)
            ->where('recipes.current_page', 3));
});

// Show

it('shows a recipe with its steps in order', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create([
        'title' => 'Soep',
        'steps' => ['Eerst', 'Dan', 'Tot slot'],
        'preparation_minutes' => 45,
        'servings' => 6,
        'houdbaar' => true,
    ]);

    actingAs($user)->get(route('recipes.show', $recipe))
        ->assertInertia(fn (Assert $page) => $page
            ->component('recipes/Show')
            ->where('recipe.id', $recipe->id)
            ->where('recipe.title', 'Soep')
            ->where('recipe.steps', ['Eerst', 'Dan', 'Tot slot'])
            ->where('recipe.preparation_minutes', 45)
            ->where('recipe.servings', 6)
            ->where('recipe.houdbaar', true)
            ->missing('recipe.user_id'));
});

it('answers 404 for another user’s recipe on every id-based route', function (string $method, string $uri) {
    $recipe = Recipe::factory()->create(['title' => 'Geheim']);

    actingAs(User::factory()->create())
        ->{$method}(str_replace('{id}', (string) $recipe->id, $uri), validRecipe(['title' => 'Gekaapt']))
        ->assertNotFound();

    assertDatabaseHas('recipes', ['id' => $recipe->id, 'title' => 'Geheim']);
})->with([
    'show' => ['get', '/recepten/{id}'],
    'edit' => ['get', '/recepten/{id}/edit'],
    'update' => ['put', '/recepten/{id}'],
    'destroy' => ['delete', '/recepten/{id}'],
]);

it('answers 404 rather than validation errors when updating another user’s recipe with invalid data', function () {
    $recipe = Recipe::factory()->create();

    actingAs(User::factory()->create())
        ->put("/recepten/{$recipe->id}", validRecipe(['title' => '', 'servings' => 0]))
        ->assertNotFound()
        ->assertSessionHasNoErrors();
});

it('answers 404 for a recipe that does not exist or has a malformed id', function (string $method, string $id) {
    actingAs(User::factory()->create())->{$method}("/recepten/{$id}")->assertNotFound();
})->with([
    'missing show' => ['get', '99999'],
    'malformed show' => ['get', 'abc'],
    'negative show' => ['get', '-1'],
    'missing update' => ['put', '99999'],
    'missing destroy' => ['delete', '99999'],
]);

it('does not leak another user’s recipe in the response body', function () {
    $recipe = Recipe::factory()->create(['title' => 'Geheim recept']);

    actingAs(User::factory()->create())->get(route('recipes.show', $recipe))
        ->assertNotFound()
        ->assertDontSee('Geheim recept');
});

// Create and store

it('renders the create page', function () {
    actingAs(User::factory()->create())->get(route('recipes.create'))
        ->assertInertia(fn (Assert $page) => $page->component('recipes/Create'));
});

it('stores a recipe for the current user and shows it', function () {
    $user = User::factory()->create();

    $response = actingAs($user)->post(route('recipes.store'), validRecipe(['houdbaar' => true]));

    $recipe = Recipe::query()->sole();
    $response->assertRedirect(route('recipes.show', $recipe));
    expect($recipe)
        ->user_id->toBe($user->id)
        ->title->toBe('Pannenkoeken')
        ->steps->toBe(['Meng het beslag', 'Bak de pannenkoeken'])
        ->preparation_minutes->toBe(30)
        ->servings->toBe(4)
        ->houdbaar->toBeTrue();
});

it('stores the title trimmed', function (string $title) {
    actingAs(User::factory()->create())->post(route('recipes.store'), validRecipe(['title' => $title]));

    expect(Recipe::query()->sole()->title)->toBe('Stamppot');
})->with([
    'spaces' => ['  Stamppot  '],
    'tabs and newlines' => ["\t\nStamppot\n"],
    'non-breaking spaces' => ["\u{00A0}Stamppot\u{00A0}"],
]);

it('keeps Unicode and emoji in the title and steps intact', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), validRecipe([
        'title' => 'Crème brûlée 🍮 日本',
        'steps' => ['Verwarm de oven op 180° 🔥', 'Laat afkoelen — niet roeren'],
    ]));

    expect(Recipe::query()->sole())
        ->title->toBe('Crème brûlée 🍮 日本')
        ->steps->toBe(['Verwarm de oven op 180° 🔥', 'Laat afkoelen — niet roeren']);
});

it('stores HTML in text fields as literal text', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), validRecipe([
        'title' => '<script>alert(1)</script>',
    ]));

    expect(Recipe::query()->sole()->title)->toBe('<script>alert(1)</script>');
});

it('accepts a title of exactly 255 characters, counted in characters not bytes', function () {
    $title = str_repeat('🍮', 255);

    actingAs(User::factory()->create())->post(route('recipes.store'), validRecipe(['title' => $title]))
        ->assertSessionHasNoErrors();

    expect(Recipe::query()->sole()->title)->toBe($title);
});

it('rejects invalid input and stores nothing', function (array $overrides, string $field) {
    actingAs(User::factory()->create())->post(route('recipes.store'), validRecipe($overrides))
        ->assertSessionHasErrors($field);

    assertDatabaseCount('recipes', 0);
})->with([
    'empty title' => [['title' => ''], 'title'],
    'whitespace title' => [['title' => "  \t\n "], 'title'],
    'non-breaking-space title' => [['title' => "\u{00A0}\u{00A0}"], 'title'],
    'null title' => [['title' => null], 'title'],
    'array title' => [['title' => ['a']], 'title'],
    'numeric title is not a string' => [['title' => 12345], 'title'],
    'title of 256 characters' => [['title' => str_repeat('a', 256)], 'title'],
    'title of 256 emoji' => [['title' => str_repeat('🍮', 256)], 'title'],

    'zero preparation time' => [['preparation_minutes' => 0], 'preparation_minutes'],
    'negative preparation time' => [['preparation_minutes' => -5], 'preparation_minutes'],
    'huge preparation time' => [['preparation_minutes' => 10081], 'preparation_minutes'],
    'overflowing preparation time' => [['preparation_minutes' => '99999999999999999999'], 'preparation_minutes'],
    'text preparation time' => [['preparation_minutes' => 'abc'], 'preparation_minutes'],
    'decimal preparation time' => [['preparation_minutes' => '1.5'], 'preparation_minutes'],
    'empty preparation time' => [['preparation_minutes' => ''], 'preparation_minutes'],
    'null preparation time' => [['preparation_minutes' => null], 'preparation_minutes'],
    'array preparation time' => [['preparation_minutes' => [1]], 'preparation_minutes'],

    'zero servings' => [['servings' => 0], 'servings'],
    'negative servings' => [['servings' => -1], 'servings'],
    'huge servings' => [['servings' => 1000], 'servings'],
    'text servings' => [['servings' => 'vier'], 'servings'],
    'decimal servings' => [['servings' => '2.5'], 'servings'],
    'empty servings' => [['servings' => ''], 'servings'],
    'array servings' => [['servings' => [4]], 'servings'],

    'text houdbaar' => [['houdbaar' => 'misschien'], 'houdbaar'],
    'array houdbaar' => [['houdbaar' => [true]], 'houdbaar'],
    'null houdbaar' => [['houdbaar' => null], 'houdbaar'],

    'steps as a string' => [['steps' => 'Meng alles'], 'steps'],
    'a step that is an array' => [['steps' => [['a']]], 'steps.0'],
    'a step that is a number' => [['steps' => [7]], 'steps.0'],
    'a step of 2001 characters' => [['steps' => [str_repeat('a', 2001)]], 'steps.0'],
    'more than 100 steps' => [['steps' => array_fill(0, 101, 'stap')], 'steps'],
]);

it('rejects a missing field', function (string $field) {
    $payload = validRecipe();
    unset($payload[$field]);

    actingAs(User::factory()->create())->post(route('recipes.store'), $payload)
        ->assertSessionHasErrors($field);

    assertDatabaseCount('recipes', 0);
})->with(['title', 'preparation_minutes', 'servings', 'houdbaar']);

it('accepts the boundary values', function () {
    $user = User::factory()->create();

    actingAs($user)->post(route('recipes.store'), validRecipe(['preparation_minutes' => 1, 'servings' => 1]))
        ->assertSessionHasNoErrors();
    actingAs($user)->post(route('recipes.store'), validRecipe(['preparation_minutes' => 10080, 'servings' => 999]))
        ->assertSessionHasNoErrors();
    actingAs($user)->post(route('recipes.store'), validRecipe(['steps' => array_fill(0, 100, 'stap'), 'title' => str_repeat('a', 255)]))
        ->assertSessionHasNoErrors();

    assertDatabaseCount('recipes', 3);
});

it('casts numeric strings and boolean strings to their real types', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), validRecipe([
        'preparation_minutes' => '25',
        'servings' => '3',
        'houdbaar' => '1',
    ]));

    expect(Recipe::query()->sole())
        ->preparation_minutes->toBe(25)
        ->servings->toBe(3)
        ->houdbaar->toBeTrue();
});

it('stores a missing steps list as an empty list', function () {
    $payload = validRecipe();
    unset($payload['steps']);

    actingAs(User::factory()->create())->post(route('recipes.store'), $payload)
        ->assertSessionHasNoErrors();

    expect(Recipe::query()->sole()->steps)->toBe([]);
});

it('drops whitespace-only steps, trims the rest and keeps their order', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), validRecipe([
        'steps' => ['  Eerst  ', '', '   ', "\t\n", "\u{00A0}", null, 'Dan  met   dubbele spaties', 'Tot slot'],
    ]));

    expect(Recipe::query()->sole()->steps)->toBe(['Eerst', 'Dan  met   dubbele spaties', 'Tot slot']);
});

it('stores steps made only of whitespace as an empty list', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), validRecipe(['steps' => ['', '  ']]));

    expect(Recipe::query()->sole()->steps)->toBe([]);
});

it('stores steps sent with arbitrary keys as a plain ordered list', function () {
    actingAs(User::factory()->create())->postJson(route('recipes.store'), validRecipe([
        'steps' => ['x' => 'Eerst', '7' => 'Dan'],
    ]));

    expect(Recipe::query()->sole()->steps)->toBe(['Eerst', 'Dan']);
});

it('ignores user_id, id and timestamps sent by the client', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    actingAs($user)->post(route('recipes.store'), validRecipe([
        'user_id' => $other->id,
        'id' => 999,
        'created_at' => '2001-01-01 00:00:00',
    ]));

    expect(Recipe::query()->sole())
        ->user_id->toBe($user->id)
        ->id->not->toBe(999);
    expect(Recipe::query()->sole()->created_at?->year)->not->toBe(2001);
});

it('speaks Dutch in validation messages', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), validRecipe(['title' => '', 'servings' => 0]))
        ->assertSessionHasErrors([
            'title' => 'Vul een titel in.',
            'servings' => 'Het aantal porties moet minimaal 1 zijn.',
        ]);
});

// Edit and update

it('renders the edit page for an owned recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();

    actingAs($user)->get(route('recipes.edit', $recipe))
        ->assertInertia(fn (Assert $page) => $page
            ->component('recipes/Edit')
            ->where('recipe.id', $recipe->id)
            ->has('recipe.steps')
            ->missing('recipe.user_id'));
});

it('updates an owned recipe and replaces its steps', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create(['steps' => ['a', 'b', 'c']]);

    actingAs($user)->put(route('recipes.update', $recipe), validRecipe([
        'title' => '  Nieuwe titel ',
        'steps' => ['Alleen deze', ' '],
        'preparation_minutes' => 10,
        'servings' => 2,
        'houdbaar' => true,
    ]))->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->refresh())
        ->title->toBe('Nieuwe titel')
        ->steps->toBe(['Alleen deze'])
        ->preparation_minutes->toBe(10)
        ->servings->toBe(2)
        ->houdbaar->toBeTrue()
        ->user_id->toBe($user->id);
});

it('can switch houdbaar off again and clear all steps', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create(['houdbaar' => true, 'steps' => ['a']]);

    actingAs($user)->put(route('recipes.update', $recipe), validRecipe(['houdbaar' => false, 'steps' => []]));

    expect($recipe->refresh())->houdbaar->toBeFalse()->steps->toBe([]);
});

it('leaves the recipe unchanged when an update is invalid', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create(['title' => 'Origineel']);

    actingAs($user)->put(route('recipes.update', $recipe), validRecipe(['title' => '   ']))
        ->assertSessionHasErrors('title');

    assertDatabaseHas('recipes', ['id' => $recipe->id, 'title' => 'Origineel']);
});

it('does not let an update move a recipe to another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();

    actingAs($user)->put(route('recipes.update', $recipe), validRecipe(['user_id' => $other->id]));

    expect($recipe->refresh()->user_id)->toBe($user->id);
});

it('validates an update with the same rules as a store', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();

    actingAs($user)->put(route('recipes.update', $recipe), validRecipe(['preparation_minutes' => 0, 'servings' => 'x']))
        ->assertSessionHasErrors(['preparation_minutes', 'servings']);
});

// Delete

it('hard deletes an owned recipe and returns to the list', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    $keep = Recipe::factory()->for($user)->create();

    actingAs($user)->delete(route('recipes.destroy', $recipe))->assertRedirect(route('recipes.index'));

    assertDatabaseMissing('recipes', ['id' => $recipe->id]);
    assertDatabaseHas('recipes', ['id' => $keep->id]);
});

it('answers 404 when deleting the same recipe twice', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();

    actingAs($user)->delete(route('recipes.destroy', $recipe));
    actingAs($user)->delete("/recepten/{$recipe->id}")->assertNotFound();
});

it('deletes a user’s recipes together with the user', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user)->count(2)->create();

    $user->delete();

    assertDatabaseCount('recipes', 0);
});

// Action

it('trims and filters when called directly, without the HTTP middleware', function () {
    $user = User::factory()->create();

    $recipe = (new SaveRecipe)->handle(
        user: $user,
        title: "  Direct \u{00A0}",
        steps: ['  een ', ' ', 'twee'],
        preparationMinutes: 5,
        servings: 2,
        houdbaar: false,
    );

    expect($recipe)
        ->exists->toBeTrue()
        ->title->toBe('Direct')
        ->steps->toBe(['een', 'twee']);
});

it('updates the given recipe instead of creating a new one', function () {
    $recipe = Recipe::factory()->create();

    $saved = (new SaveRecipe)->handle(
        user: $recipe->user,
        title: 'Bijgewerkt',
        steps: [],
        preparationMinutes: 5,
        servings: 2,
        houdbaar: true,
        recipe: $recipe,
    );

    expect($saved->is($recipe))->toBeTrue();
    assertDatabaseCount('recipes', 1);
});
