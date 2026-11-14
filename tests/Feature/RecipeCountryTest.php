<?php

declare(strict_types=1);

use App\Actions\Recipes\SaveRecipe;
use App\Models\Recipe;
use App\Models\User;
use App\Support\RecipeFilters;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function countryPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Ratatouille',
        'steps' => ['Stoven'],
        'preparation_minutes' => 30,
        'servings' => 4,
        'houdbaar' => false,
    ], $overrides);
}

/**
 * @return list<string>
 */
function titlesFor(string $query): array
{
    /** @var list<string> */
    return collect(test()->get('/recepten?'.$query)->assertOk()->viewData('page')['props']['recipes']['data'])
        ->pluck('title')
        ->sort()
        ->values()
        ->all();
}

it('stores a recipe with a country, trimmed', function () {
    actingAs(User::factory()->create())
        ->post(route('recipes.store'), countryPayload(['country' => "  Frankrijk\u{00A0}"]))
        ->assertSessionHasNoErrors();

    expect(Recipe::query()->sole()->country)->toBe('Frankrijk');
});

it('stores a recipe without a country', function (mixed $country) {
    $payload = countryPayload();
    if ($country !== 'absent') {
        $payload['country'] = $country;
    }

    actingAs(User::factory()->create())->post(route('recipes.store'), $payload)->assertSessionHasNoErrors();

    expect(Recipe::query()->sole()->country)->toBeNull();
})->with(['absent', 'null' => null, 'empty' => '', 'blank' => '   ']);

it('updates and clears the country', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create(['country' => 'Italië']);

    actingAs($user)->put(route('recipes.update', $recipe), countryPayload(['country' => 'Spanje']))->assertSessionHasNoErrors();
    expect($recipe->refresh()->country)->toBe('Spanje');

    actingAs($user)->put(route('recipes.update', $recipe), countryPayload(['country' => '']))->assertSessionHasNoErrors();
    expect($recipe->refresh()->country)->toBeNull();
});

it('rejects a country that is too long or not text', function (mixed $country, string $message) {
    actingAs(User::factory()->create())
        ->post(route('recipes.store'), countryPayload(['country' => $country]))
        ->assertSessionHasErrors(['country' => $message]);
})->with([
    'too long' => [str_repeat('a', 61), 'Het land mag maximaal 60 tekens lang zijn.'],
    'array' => [['Frankrijk'], 'Het land moet tekst zijn.'],
]);

it('accepts a country of 60 characters', function () {
    actingAs(User::factory()->create())
        ->post(route('recipes.store'), countryPayload(['country' => str_repeat('a', 60)]))
        ->assertSessionHasNoErrors();
});

it('filters the list on an exact country', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user)->create(['title' => 'Quiche', 'country' => 'Frankrijk']);
    Recipe::factory()->for($user)->create(['title' => 'Pasta', 'country' => 'Italië']);
    Recipe::factory()->for($user)->create(['title' => 'Stamppot']);
    actingAs($user);

    expect(titlesFor('country=Frankrijk'))->toBe(['Quiche'])
        ->and(titlesFor('country=Frank'))->toBe([])
        ->and(titlesFor('country='))->toBe(['Pasta', 'Quiche', 'Stamppot'])
        ->and(titlesFor('country[]=Frankrijk'))->toBe(['Pasta', 'Quiche', 'Stamppot']);
});

it('combines the country filter with other filters', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user)->create(['title' => 'Quiche', 'country' => 'Frankrijk', 'houdbaar' => true]);
    Recipe::factory()->for($user)->create(['title' => 'Soep', 'country' => 'Frankrijk', 'houdbaar' => false]);
    actingAs($user);

    expect(titlesFor('country=Frankrijk&houdbaar=1'))->toBe(['Quiche']);
});

it('never shows the recipes of another user through the country filter', function () {
    Recipe::factory()->create(['title' => 'Vreemd', 'country' => 'Frankrijk']);
    actingAs(User::factory()->create());

    expect(titlesFor('country=Frankrijk'))->toBe([]);
});

it('exposes the filter and the sorted distinct countries of the user', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user)->count(2)->create(['country' => 'Frankrijk']);
    Recipe::factory()->for($user)->create(['country' => 'Armenië']);
    Recipe::factory()->for($user)->create();
    Recipe::factory()->create(['country' => 'Zweden']);

    actingAs($user)->get('/recepten?country=Frankrijk')->assertInertia(fn (Assert $page) => $page
        ->component('recipes/Index')
        ->where('knownCountries', ['Armenië', 'Frankrijk'])
        ->where('filters.country', 'Frankrijk')
        ->where('filters.active', true));
});

it('reports no country filter by default', function () {
    actingAs(User::factory()->create())->get('/recepten')->assertInertia(fn (Assert $page) => $page
        ->where('filters.country', null)
        ->where('filters.active', false)
        ->where('knownCountries', []));
});

it('passes the known countries to the create and edit pages', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create(['country' => 'Spanje']);

    actingAs($user)->get(route('recipes.create'))->assertInertia(fn (Assert $page) => $page->where('knownCountries', ['Spanje']));
    actingAs($user)->get(route('recipes.edit', $recipe))->assertInertia(fn (Assert $page) => $page->where('knownCountries', ['Spanje']));
});

it('returns the country with the recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create(['country' => 'Spanje']);

    actingAs($user)->get(route('recipes.show', $recipe))->assertInertia(fn (Assert $page) => $page->where('recipe.country', 'Spanje'));
});

it('trims and clears the country when the action is called directly', function () {
    $user = User::factory()->create();
    $recipe = (new SaveRecipe)->handle(
        user: $user,
        title: 'Direct',
        steps: [],
        preparationMinutes: 5,
        servings: 2,
        houdbaar: false,
        ingredients: [],
        country: ' Griekenland ',
    );

    expect($recipe->country)->toBe('Griekenland');

    $blank = (new SaveRecipe)->handle(
        user: $user,
        title: 'Direct',
        steps: [],
        preparationMinutes: 5,
        servings: 2,
        houdbaar: false,
        ingredients: [],
        country: '  ',
        recipe: $recipe,
    );

    expect($blank->country)->toBeNull();
});

it('treats a blank country filter as none', function () {
    $filters = new RecipeFilters(search: null, tags: [], maxMinutes: null, houdbaar: false, country: '  ');

    expect($filters->country)->toBeNull()->and($filters->isActive())->toBeFalse();
});
