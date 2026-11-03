<?php

declare(strict_types=1);

use App\Actions\Recipes\SaveRecipe;
use App\Enums\IngredientUnit;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function recipeWithIngredients(array $ingredients, array $overrides = []): array
{
    return array_merge([
        'title' => 'Pannenkoeken',
        'steps' => ['Meng het beslag'],
        'preparation_minutes' => 30,
        'servings' => 4,
        'houdbaar' => false,
        'ingredients' => $ingredients,
    ], $overrides);
}

/**
 * @return list<array{string, string|null, string}>
 */
function storedIngredients(): array
{
    return Ingredient::query()->orderBy('recipe_id')->orderBy('position')->get()
        ->map(fn (Ingredient $ingredient): array => [
            (string) $ingredient->quantity,
            $ingredient->unit?->value,
            $ingredient->name,
        ])
        ->all();
}

// Storing

it('stores ingredients in the order they were sent', function () {
    $user = User::factory()->create();

    actingAs($user)->post(route('recipes.store'), recipeWithIngredients([
        ['quantity' => '250', 'unit' => 'g', 'name' => 'Bloem'],
        ['quantity' => '2', 'unit' => 'stuks', 'name' => 'Eieren'],
        ['quantity' => '1.5', 'unit' => 'eetlepel', 'name' => 'Suiker'],
        ['quantity' => null, 'unit' => null, 'name' => 'Zout naar smaak'],
    ]))->assertSessionHasNoErrors();

    $recipe = Recipe::query()->sole();
    expect($recipe->ingredients->pluck('name')->all())->toBe(['Bloem', 'Eieren', 'Suiker', 'Zout naar smaak'])
        ->and($recipe->ingredients->pluck('position')->all())->toBe([0, 1, 2, 3])
        ->and($recipe->ingredients[0])->quantity->toBe(250.0)->unit->toBe(IngredientUnit::Gram)
        ->and($recipe->ingredients[2])->quantity->toBe(1.5)->unit->toBe(IngredientUnit::Tablespoon)
        ->and($recipe->ingredients[3])->quantity->toBeNull()->unit->toBeNull();
});

it('stores a recipe without ingredients', function (mixed $ingredients) {
    $payload = recipeWithIngredients([]);
    if ($ingredients === 'absent') {
        unset($payload['ingredients']);
    } else {
        $payload['ingredients'] = $ingredients;
    }

    actingAs(User::factory()->create())->post(route('recipes.store'), $payload)->assertSessionHasNoErrors();

    assertDatabaseCount('recipes', 1);
    assertDatabaseCount('ingredients', 0);
})->with([
    'absent' => ['absent'],
    'empty list' => [[]],
    'null' => [null],
]);

it('allows every unit', function (IngredientUnit $unit) {
    actingAs(User::factory()->create())->post(route('recipes.store'), recipeWithIngredients([
        ['quantity' => '1', 'unit' => $unit->value, 'name' => 'Iets'],
    ]))->assertSessionHasNoErrors();

    expect(Ingredient::query()->sole()->unit)->toBe($unit);
})->with(IngredientUnit::cases());

it('allows a quantity without a unit and a name on its own', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), recipeWithIngredients([
        ['quantity' => '3', 'unit' => '', 'name' => 'teentjes knoflook'],
        ['quantity' => '', 'unit' => '', 'name' => 'Peper'],
    ]))->assertSessionHasNoErrors();

    expect(storedIngredients())->toBe([['3', null, 'teentjes knoflook'], ['', null, 'Peper']]);
});

it('drops completely blank rows and keeps the order of the rest', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), recipeWithIngredients([
        ['quantity' => '', 'unit' => '', 'name' => ''],
        ['quantity' => '1', 'unit' => 'kg', 'name' => 'Aardappelen'],
        ['quantity' => null, 'unit' => null, 'name' => "  \t "],
        ['quantity' => ' ', 'unit' => '', 'name' => "\u{00A0}\u{200B}"],
        ['quantity' => '2', 'unit' => 'l', 'name' => 'Melk'],
    ]))->assertSessionHasNoErrors();

    expect(Ingredient::query()->orderBy('position')->get()->map(fn (Ingredient $i) => [$i->position, $i->name])->all())
        ->toBe([[0, 'Aardappelen'], [1, 'Melk']]);
});

it('trims the name, including non-breaking and zero-width spaces', function (string $name) {
    actingAs(User::factory()->create())->post(route('recipes.store'), recipeWithIngredients([
        ['quantity' => '1', 'unit' => 'stuks', 'name' => $name],
    ]))->assertSessionHasNoErrors();

    expect(Ingredient::query()->sole()->name)->toBe('Ui');
})->with([
    'spaces' => ['  Ui  '],
    'tabs and newlines' => ["\t\nUi\n"],
    'non-breaking spaces' => ["\u{00A0}Ui\u{00A0}"],
    'zero-width spaces' => ["\u{200B}Ui\u{200B}"],
]);

it('keeps a name that is the string zero', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), recipeWithIngredients([
        ['quantity' => '1', 'unit' => 'g', 'name' => '0'],
    ]))->assertSessionHasNoErrors();

    expect(Ingredient::query()->sole()->name)->toBe('0');
});

it('keeps Unicode, emoji and HTML in names as literal text', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), recipeWithIngredients([
        ['quantity' => '1', 'unit' => 'stuks', 'name' => 'Crème fraîche 🧈 日本'],
        ['quantity' => '1', 'unit' => 'stuks', 'name' => '<b>vet</b>'],
    ]))->assertSessionHasNoErrors();

    expect(Ingredient::query()->orderBy('position')->pluck('name')->all())->toBe(['Crème fraîche 🧈 日本', '<b>vet</b>']);
});

it('accepts a name of exactly 255 characters, counted in characters not bytes', function () {
    $name = str_repeat('🧈', 255);

    actingAs(User::factory()->create())->post(route('recipes.store'), recipeWithIngredients([
        ['quantity' => '1', 'unit' => 'stuks', 'name' => $name],
    ]))->assertSessionHasNoErrors();

    expect(Ingredient::query()->sole()->name)->toBe($name);
});

it('allows duplicate names', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), recipeWithIngredients([
        ['quantity' => '1', 'unit' => 'g', 'name' => 'Zout'],
        ['quantity' => '2', 'unit' => 'g', 'name' => 'Zout'],
    ]))->assertSessionHasNoErrors();

    assertDatabaseCount('ingredients', 2);
});

it('accepts exactly 100 ingredients', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), recipeWithIngredients(
        array_fill(0, 100, ['quantity' => '1', 'unit' => 'g', 'name' => 'Zout']),
    ))->assertSessionHasNoErrors();

    assertDatabaseCount('ingredients', 100);
});

it('normalises quantities to a plain two-decimal number', function (mixed $sent, string $stored) {
    actingAs(User::factory()->create())->post(route('recipes.store'), recipeWithIngredients([
        ['quantity' => $sent, 'unit' => 'g', 'name' => 'Bloem'],
    ]))->assertSessionHasNoErrors();

    expect(storedIngredients())->toBe([[$stored, 'g', 'Bloem']]);
})->with([
    'integer' => ['250', '250'],
    'dot decimal' => ['1.5', '1.5'],
    'Dutch comma decimal' => ['1,5', '1.5'],
    'two decimals with comma' => ['0,25', '0.25'],
    'leading comma' => [',5', '0.5'],
    'leading dot' => ['.5', '0.5'],
    'JSON number' => [2.5, '2.5'],
    'JSON integer' => [3, '3'],
    'upper bound' => ['100000', '100000'],
    'smallest' => ['0.01', '0.01'],
    'surrounding spaces' => [' 4 ', '4'],
    'zero-width spaces around' => ["\u{200B}4\u{200B}", '4'],
]);

// Rejections

it('rejects invalid ingredients and stores nothing', function (array $ingredients, string $field) {
    actingAs(User::factory()->create())->post(route('recipes.store'), recipeWithIngredients($ingredients))
        ->assertSessionHasErrors($field);

    assertDatabaseCount('recipes', 0);
    assertDatabaseCount('ingredients', 0);
})->with([
    'quantity 0' => [[['quantity' => '0', 'unit' => 'g', 'name' => 'x']], 'ingredients.0.quantity'],
    'quantity 0,00' => [[['quantity' => '0,00', 'unit' => 'g', 'name' => 'x']], 'ingredients.0.quantity'],
    'negative quantity' => [[['quantity' => '-1', 'unit' => 'g', 'name' => 'x']], 'ingredients.0.quantity'],
    'huge quantity' => [[['quantity' => '100001', 'unit' => 'g', 'name' => 'x']], 'ingredients.0.quantity'],
    'overflowing quantity' => [[['quantity' => '99999999999999999999', 'unit' => 'g', 'name' => 'x']], 'ingredients.0.quantity'],
    'three decimals' => [[['quantity' => '1.234', 'unit' => 'g', 'name' => 'x']], 'ingredients.0.quantity'],
    'three decimals with comma' => [[['quantity' => '1,234', 'unit' => 'g', 'name' => 'x']], 'ingredients.0.quantity'],
    'two commas' => [[['quantity' => '1,2,3', 'unit' => 'g', 'name' => 'x']], 'ingredients.0.quantity'],
    'comma and dot' => [[['quantity' => '1.000,5', 'unit' => 'g', 'name' => 'x']], 'ingredients.0.quantity'],
    'text quantity' => [[['quantity' => 'veel', 'unit' => 'g', 'name' => 'x']], 'ingredients.0.quantity'],
    'exponent quantity' => [[['quantity' => '1e3', 'unit' => 'g', 'name' => 'x']], 'ingredients.0.quantity'],
    'infinity quantity' => [[['quantity' => 'INF', 'unit' => 'g', 'name' => 'x']], 'ingredients.0.quantity'],
    'array quantity' => [[['quantity' => [1], 'unit' => 'g', 'name' => 'x']], 'ingredients.0.quantity'],
    'fraction quantity' => [[['quantity' => '1/2', 'unit' => 'g', 'name' => 'x']], 'ingredients.0.quantity'],

    'unknown unit' => [[['quantity' => '1', 'unit' => 'liter', 'name' => 'x']], 'ingredients.0.unit'],
    'unit in other case' => [[['quantity' => '1', 'unit' => 'G', 'name' => 'x']], 'ingredients.0.unit'],
    'array unit' => [[['quantity' => '1', 'unit' => ['g'], 'name' => 'x']], 'ingredients.0.unit'],
    'numeric unit' => [[['quantity' => '1', 'unit' => 5, 'name' => 'x']], 'ingredients.0.unit'],
    'unit without quantity' => [[['quantity' => '', 'unit' => 'g', 'name' => 'x']], 'ingredients.0.quantity'],

    'quantity without name' => [[['quantity' => '1', 'unit' => 'g', 'name' => '']], 'ingredients.0.name'],
    'unit and quantity with whitespace-only name' => [[['quantity' => '1', 'unit' => 'g', 'name' => "  \t\n "]], 'ingredients.0.name'],
    'quantity with non-breaking-space name' => [[['quantity' => '1', 'unit' => 'g', 'name' => "\u{00A0}\u{200B}"]], 'ingredients.0.name'],
    'unit without name' => [[['quantity' => '1', 'unit' => 'g']], 'ingredients.0.name'],
    'numeric name' => [[['quantity' => '1', 'unit' => 'g', 'name' => 12]], 'ingredients.0.name'],
    'array name' => [[['quantity' => '1', 'unit' => 'g', 'name' => ['a']]], 'ingredients.0.name'],
    'name of 256 characters' => [[['quantity' => '1', 'unit' => 'g', 'name' => str_repeat('a', 256)]], 'ingredients.0.name'],
    'name of 256 emoji' => [[['quantity' => '1', 'unit' => 'g', 'name' => str_repeat('🧈', 256)]], 'ingredients.0.name'],

    'ingredients as a string' => [['Bloem'], 'ingredients.0'],
    'a row that is a number' => [[5], 'ingredients.0'],
    'second row invalid' => [[['quantity' => '1', 'unit' => 'g', 'name' => 'ok'], ['quantity' => '-1', 'unit' => 'g', 'name' => 'x']], 'ingredients.1.quantity'],
    '101 rows' => [array_fill(0, 101, ['quantity' => '1', 'unit' => 'g', 'name' => 'x']), 'ingredients'],
]);

it('rejects ingredients sent as a string or a scalar', function (mixed $ingredients) {
    actingAs(User::factory()->create())->post(route('recipes.store'), recipeWithIngredients([], ['ingredients' => $ingredients]))
        ->assertSessionHasErrors('ingredients');

    assertDatabaseCount('recipes', 0);
})->with(['a string' => ['Bloem en eieren'], 'a number' => [5], 'a boolean' => [true]]);

it('counts blank rows towards the 100 row limit', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), recipeWithIngredients(
        array_fill(0, 101, ['quantity' => '', 'unit' => '', 'name' => '']),
    ))->assertSessionHasErrors('ingredients');
});

it('speaks Dutch in ingredient validation messages', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), recipeWithIngredients([
        ['quantity' => '1', 'unit' => 'g', 'name' => 'ok'],
        ['quantity' => '0', 'unit' => 'liter', 'name' => ''],
    ]))->assertSessionHasErrors([
        'ingredients.1.name' => 'Vul bij ingrediënt 2 een naam in.',
        'ingredients.1.quantity' => 'De hoeveelheid van ingrediënt 2 moet groter zijn dan 0.',
        'ingredients.1.unit' => 'Kies bij ingrediënt 2 een eenheid uit de lijst.',
    ]);
});

it('explains a unit without a quantity in Dutch', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), recipeWithIngredients([
        ['quantity' => '', 'unit' => 'g', 'name' => 'Zout'],
    ]))->assertSessionHasErrors(['ingredients.0.quantity' => 'Vul bij ingrediënt 1 een hoeveelheid in of kies geen eenheid.']);
});

it('ignores ids, recipe ids and positions sent by the client', function () {
    $user = User::factory()->create();
    $foreign = Ingredient::factory()->for(Recipe::factory()->create())->create(['name' => 'Van een ander']);

    actingAs($user)->post(route('recipes.store'), recipeWithIngredients([
        ['id' => $foreign->id, 'recipe_id' => $foreign->recipe_id, 'position' => 50, 'quantity' => '1', 'unit' => 'g', 'name' => 'Eigen'],
    ]))->assertSessionHasNoErrors();

    $mine = Recipe::query()->where('user_id', $user->id)->sole();
    expect($mine->ingredients)->toHaveCount(1)
        ->and($mine->ingredients[0])->name->toBe('Eigen')->position->toBe(0)->id->not->toBe($foreign->id);
    assertDatabaseHas('ingredients', ['id' => $foreign->id, 'name' => 'Van een ander', 'recipe_id' => $foreign->recipe_id]);
});

// Show and edit

it('shows ingredients in position order with the recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create(['servings' => 4]);
    Ingredient::factory()->for($recipe)->create(['position' => 1, 'quantity' => 2, 'unit' => 'stuks', 'name' => 'Eieren']);
    Ingredient::factory()->for($recipe)->create(['position' => 0, 'quantity' => 250.5, 'unit' => 'g', 'name' => 'Bloem']);
    Ingredient::factory()->for($recipe)->create(['position' => 2, 'quantity' => null, 'unit' => null, 'name' => 'Zout']);

    actingAs($user)->get(route('recipes.show', $recipe))
        ->assertInertia(fn (Assert $page) => $page
            ->component('recipes/Show')
            ->has('recipe.ingredients', 3)
            ->where('recipe.ingredients.0.name', 'Bloem')
            ->where('recipe.ingredients.0.quantity', 250.5)
            ->where('recipe.ingredients.0.unit', 'g')
            ->where('recipe.ingredients.1.name', 'Eieren')
            ->where('recipe.ingredients.1.unit', 'stuks')
            ->where('recipe.ingredients.2.quantity', null)
            ->where('recipe.ingredients.2.unit', null)
            ->missing('recipe.ingredients.0.recipe_id')
            ->missing('recipe.user_id'));
});

it('shows a recipe without ingredients as an empty list', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();

    actingAs($user)->get(route('recipes.show', $recipe))
        ->assertInertia(fn (Assert $page) => $page->has('recipe.ingredients', 0));
});

it('sends ingredients to the edit page', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    Ingredient::factory()->for($recipe)->create(['name' => 'Bloem']);

    actingAs($user)->get(route('recipes.edit', $recipe))
        ->assertInertia(fn (Assert $page) => $page->has('recipe.ingredients', 1)->where('recipe.ingredients.0.name', 'Bloem'));
});

it('does not put ingredients on the recipe list', function () {
    $user = User::factory()->create();
    Ingredient::factory()->for(Recipe::factory()->for($user))->create();

    actingAs($user)->get(route('recipes.index'))
        ->assertInertia(fn (Assert $page) => $page->missing('recipes.data.0.ingredients'));
});

// Update

it('replaces the whole set of ingredients on update', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    Ingredient::factory()->for($recipe)->count(3)->create();

    actingAs($user)->put(route('recipes.update', $recipe), recipeWithIngredients([
        ['quantity' => '5', 'unit' => 'ml', 'name' => 'Nieuw'],
    ]))->assertRedirect(route('recipes.show', $recipe));

    expect(storedIngredients())->toBe([['5', 'ml', 'Nieuw']]);
});

it('reorders, adds and removes ingredients on update', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    Ingredient::factory()->for($recipe)->create(['position' => 0, 'name' => 'A']);
    Ingredient::factory()->for($recipe)->create(['position' => 1, 'name' => 'B']);

    actingAs($user)->put(route('recipes.update', $recipe), recipeWithIngredients([
        ['quantity' => '', 'unit' => '', 'name' => 'C'],
        ['quantity' => '', 'unit' => '', 'name' => 'B'],
    ]));

    expect($recipe->refresh()->ingredients->pluck('name', 'position')->all())->toBe([0 => 'C', 1 => 'B']);
});

it('clears all ingredients when an update sends none', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    Ingredient::factory()->for($recipe)->count(2)->create();

    actingAs($user)->put(route('recipes.update', $recipe), recipeWithIngredients([]));

    assertDatabaseCount('ingredients', 0);
});

it('leaves the ingredients unchanged when an update is invalid', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    Ingredient::factory()->for($recipe)->create(['name' => 'Origineel']);

    actingAs($user)->put(route('recipes.update', $recipe), recipeWithIngredients([
        ['quantity' => '-1', 'unit' => 'g', 'name' => 'Nieuw'],
    ]))->assertSessionHasErrors('ingredients.0.quantity');

    expect(storedIngredients())->toBe([['1', 'g', 'Origineel']]);
});

it('only replaces the ingredients of the recipe being updated', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    $other = Recipe::factory()->for($user)->create();
    Ingredient::factory()->for($other)->create(['name' => 'Blijft']);

    actingAs($user)->put(route('recipes.update', $recipe), recipeWithIngredients([]));

    assertDatabaseHas('ingredients', ['recipe_id' => $other->id, 'name' => 'Blijft']);
});

// Isolation

it('answers 404 for another user’s recipe and leaves its ingredients alone', function (string $method, string $uri) {
    $recipe = Recipe::factory()->create();
    $ingredient = Ingredient::factory()->for($recipe)->create(['name' => 'Geheim']);

    actingAs(User::factory()->create())
        ->{$method}(str_replace('{id}', (string) $recipe->id, $uri), recipeWithIngredients([
            ['quantity' => '1', 'unit' => 'g', 'name' => 'Gekaapt'],
        ]))
        ->assertNotFound()
        ->assertDontSee('Geheim');

    assertDatabaseHas('ingredients', ['id' => $ingredient->id, 'name' => 'Geheim']);
    assertDatabaseCount('ingredients', 1);
})->with([
    'show' => ['get', '/recepten/{id}'],
    'edit' => ['get', '/recepten/{id}/edit'],
    'update' => ['put', '/recepten/{id}'],
    'destroy' => ['delete', '/recepten/{id}'],
]);

it('answers 404 rather than validation errors for invalid ingredients on another user’s recipe', function () {
    $recipe = Recipe::factory()->create();

    actingAs(User::factory()->create())
        ->put("/recepten/{$recipe->id}", recipeWithIngredients([['quantity' => '-5', 'unit' => 'nope', 'name' => '']]))
        ->assertNotFound()
        ->assertSessionHasNoErrors();
});

it('cannot move another user’s ingredient into your own recipe by id', function () {
    $user = User::factory()->create();
    $mine = Recipe::factory()->for($user)->create();
    $foreign = Ingredient::factory()->for(Recipe::factory()->create())->create(['name' => 'Van een ander']);

    actingAs($user)->put(route('recipes.update', $mine), recipeWithIngredients([
        ['id' => $foreign->id, 'quantity' => '1', 'unit' => 'g', 'name' => 'Gekaapt'],
    ]));

    expect($foreign->refresh())->name->toBe('Van een ander')->recipe_id->not->toBe($mine->id);
    expect($mine->ingredients->pluck('name')->all())->toBe(['Gekaapt']);
});

// Delete

it('deletes the ingredients together with the recipe and keeps other recipes’ ingredients', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    $keep = Recipe::factory()->for($user)->create();
    Ingredient::factory()->for($recipe)->count(2)->create();
    $kept = Ingredient::factory()->for($keep)->create();

    actingAs($user)->delete(route('recipes.destroy', $recipe))->assertRedirect(route('recipes.index'));

    assertDatabaseMissing('ingredients', ['recipe_id' => $recipe->id]);
    assertDatabaseHas('ingredients', ['id' => $kept->id]);
});

it('deletes ingredients when the user is deleted', function () {
    $user = User::factory()->create();
    Ingredient::factory()->for(Recipe::factory()->for($user))->count(2)->create();

    $user->delete();

    assertDatabaseCount('ingredients', 0);
});

// Action

it('trims, filters and numbers ingredients when called directly, without the HTTP middleware', function () {
    $recipe = (new SaveRecipe)->handle(
        user: User::factory()->create(),
        title: 'Direct',
        steps: [],
        preparationMinutes: 5,
        servings: 2,
        houdbaar: false,
        ingredients: [
            ['quantity' => '2', 'unit' => 'g', 'name' => "  Zout\u{00A0}"],
            ['quantity' => null, 'unit' => null, 'name' => ' '],
            'not a row',
            ['name' => 'Peper'],
            ['quantity' => 'abc', 'unit' => 'bogus', 'name' => 'Kruiden'],
        ],
    );

    expect($recipe->ingredients->map(fn (Ingredient $i) => [$i->position, $i->name, $i->quantity, $i->unit])->all())
        ->toBe([[0, 'Zout', 2.0, IngredientUnit::Gram], [1, 'Peper', null, null], [2, 'Kruiden', null, null]]);
});

it('rolls back the recipe when saving ingredients fails', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create(['title' => 'Origineel']);
    Ingredient::factory()->for($recipe)->create(['name' => 'Blijft']);

    Ingredient::creating(fn () => throw new RuntimeException('boom'));

    expect(fn () => (new SaveRecipe)->handle(
        user: $user,
        title: 'Nieuw',
        steps: [],
        preparationMinutes: 5,
        servings: 2,
        houdbaar: false,
        ingredients: [['quantity' => '1', 'unit' => 'g', 'name' => 'x']],
        recipe: $recipe,
    ))->toThrow(RuntimeException::class);

    assertDatabaseHas('recipes', ['id' => $recipe->id, 'title' => 'Origineel']);
    assertDatabaseHas('ingredients', ['recipe_id' => $recipe->id, 'name' => 'Blijft']);
});
