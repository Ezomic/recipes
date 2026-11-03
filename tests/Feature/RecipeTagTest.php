<?php

declare(strict_types=1);

use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function taggedRecipe(mixed $tags, array $overrides = []): array
{
    return array_merge([
        'title' => 'Pannenkoeken',
        'steps' => ['Bakken'],
        'preparation_minutes' => 30,
        'servings' => 4,
        'houdbaar' => false,
        'tags' => $tags,
    ], $overrides);
}

/**
 * @return list<string>
 */
function tagNames(Recipe $recipe): array
{
    return $recipe->tags()->orderBy('name')->pluck('name')->all();
}

/**
 * @return list<string>
 */
function allTagNames(): array
{
    return Tag::query()->orderBy('name')->pluck('name')->all();
}

// Storing

it('creates the tags with the recipe and attaches them', function () {
    $user = User::factory()->create();

    actingAs($user)->post(route('recipes.store'), taggedRecipe(['kamperen', 'vegetarisch', 'eenpans']))
        ->assertSessionHasNoErrors();

    $recipe = Recipe::query()->sole();
    expect(tagNames($recipe))->toBe(['eenpans', 'kamperen', 'vegetarisch'])
        ->and(Tag::query()->pluck('user_id')->unique()->all())->toBe([$user->id]);
});

it('stores a recipe without tags', function (mixed $tags) {
    $payload = taggedRecipe([]);
    if ($tags === 'absent') {
        unset($payload['tags']);
    } else {
        $payload['tags'] = $tags;
    }

    actingAs(User::factory()->create())->post(route('recipes.store'), $payload)->assertSessionHasNoErrors();

    assertDatabaseCount('recipes', 1);
    assertDatabaseCount('tags', 0);
})->with([
    'absent' => ['absent'],
    'empty list' => [[]],
    'null' => [null],
]);

it('stores one tag whatever the case, spacing or invisible characters', function (string $variant) {
    actingAs(User::factory()->create())->post(route('recipes.store'), taggedRecipe(['kamperen', $variant]))
        ->assertSessionHasNoErrors();

    expect(allTagNames())->toBe(['kamperen']);
})->with([
    'capitalised' => ['Kamperen'],
    'upper case' => ['KAMPEREN'],
    'trailing space' => ['kamperen '],
    'leading and trailing tabs' => ["\tkamperen\n"],
    'non-breaking spaces' => ["\u{00A0}Kamperen\u{00A0}"],
    'zero-width spaces' => ["\u{200B}kamperen\u{200B}"],
    'zero-width inside the word' => ["kamp\u{200B}eren"],
    'byte order mark' => ["\u{FEFF}kamperen"],
]);

it('collapses internal whitespace to single spaces', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), taggedRecipe([
        "een   pans\t gerecht",
        "snel\u{00A0}\u{00A0}klaar",
    ]))->assertSessionHasNoErrors();

    expect(allTagNames())->toBe(['een pans gerecht', 'snel klaar']);
});

it('lowercases Unicode tags', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), taggedRecipe(['CRÈME Brûlée', 'ÉÉN']))
        ->assertSessionHasNoErrors();

    expect(allTagNames())->toBe(['crème brûlée', 'één']);
});

it('keeps emoji, HTML and SQL-looking text as literal tag text', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), taggedRecipe([
        '🍕 pizza',
        '<b>vet</b>',
        "'; drop table tags; --",
        '100%_\\',
    ]))->assertSessionHasNoErrors();

    expect(allTagNames())->toBe(["'; drop table tags; --", '100%_\\', '<b>vet</b>', '🍕 pizza']);
});

it('keeps a tag that is the string zero', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), taggedRecipe(['0']))->assertSessionHasNoErrors();

    expect(allTagNames())->toBe(['0']);
});

it('drops blank tags', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), taggedRecipe([
        '', '   ', "\u{00A0}\u{200B}", null, 'soep',
    ]))->assertSessionHasNoErrors();

    expect(allTagNames())->toBe(['soep']);
});

it('collapses duplicates within one submission', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), taggedRecipe(['soep', 'Soep', ' soep ', 'brood']))
        ->assertSessionHasNoErrors();

    expect(allTagNames())->toBe(['brood', 'soep'])
        ->and(Recipe::query()->sole()->tags)->toHaveCount(2);
});

it('counts the limit after duplicates and blanks are gone', function () {
    $tags = array_merge(array_fill(0, 30, 'soep'), array_fill(0, 30, ' '), ['brood']);

    actingAs(User::factory()->create())->post(route('recipes.store'), taggedRecipe($tags))->assertSessionHasNoErrors();

    expect(allTagNames())->toBe(['brood', 'soep']);
});

it('allows 20 tags and rejects 21', function () {
    $twenty = array_map(fn (int $n): string => "tag{$n}", range(1, 20));

    actingAs(User::factory()->create())->post(route('recipes.store'), taggedRecipe($twenty))->assertSessionHasNoErrors();
    expect(Tag::query()->count())->toBe(20);

    $this->post(route('recipes.store'), taggedRecipe([...$twenty, 'tag21'], ['title' => 'Tweede']))
        ->assertSessionHasErrors('tags');

    assertDatabaseCount('recipes', 1);
    assertDatabaseCount('tags', 20);
});

it('allows a tag of 50 characters and rejects one of 51', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), taggedRecipe([str_repeat('é', 50)]))
        ->assertSessionHasNoErrors();

    $this->post(route('recipes.store'), taggedRecipe([str_repeat('é', 51)], ['title' => 'Tweede']))
        ->assertSessionHasErrors('tags.0');

    assertDatabaseCount('recipes', 1);
    expect(Tag::query()->sole()->name)->toBe(str_repeat('é', 50));
});

it('measures the length after normalising', function () {
    actingAs(User::factory()->create())->post(route('recipes.store'), taggedRecipe([
        str_repeat('a', 50).str_repeat(' ', 40),
    ]))->assertSessionHasNoErrors();

    expect(Tag::query()->sole()->name)->toBe(str_repeat('a', 50));
});

it('rejects tags of the wrong type without creating anything', function (mixed $tags) {
    actingAs(User::factory()->create())->post(route('recipes.store'), taggedRecipe($tags))
        ->assertSessionHasErrors();

    assertDatabaseCount('recipes', 0);
    assertDatabaseCount('tags', 0);
})->with([
    'a string' => ['kamperen'],
    'an integer' => [5],
    'a nested array' => [[['kamperen']]],
    'an id' => [[1, 2]],
    'a keyed object' => [[['id' => 1, 'name' => 'soep']]],
    'a boolean' => [[true]],
]);

it('ignores a tag_ids field', function () {
    $other = User::factory()->create();
    $foreign = Tag::factory()->for($other)->create(['name' => 'geheim']);

    actingAs(User::factory()->create())->post(route('recipes.store'), taggedRecipe(['soep'], ['tag_ids' => [$foreign->id]]))
        ->assertSessionHasNoErrors();

    expect(Recipe::query()->sole()->tags->pluck('name')->all())->toBe(['soep'])
        ->and($foreign->recipes()->count())->toBe(0);
});

// Per user

it('keeps tags per user, so the same name is two tags', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    actingAs($alice)->post(route('recipes.store'), taggedRecipe(['soep']));
    actingAs($bob)->post(route('recipes.store'), taggedRecipe(['soep']));

    assertDatabaseCount('tags', 2);
    assertDatabaseHas('tags', ['user_id' => $alice->id, 'name' => 'soep']);
    assertDatabaseHas('tags', ['user_id' => $bob->id, 'name' => 'soep']);
});

it('reuses an existing tag of the user instead of creating a second one', function () {
    $user = User::factory()->create();
    $existing = Tag::factory()->for($user)->create(['name' => 'soep']);

    actingAs($user)->post(route('recipes.store'), taggedRecipe(['Soep']));

    assertDatabaseCount('tags', 1);
    expect(Recipe::query()->sole()->tags->sole()->is($existing))->toBeTrue();
});

it('enforces a unique name per user in the database', function () {
    $user = User::factory()->create();
    Tag::factory()->for($user)->create(['name' => 'soep']);

    expect(fn () => Tag::factory()->for($user)->create(['name' => 'soep']))->toThrow(QueryException::class);
});

// Updating

it('replaces the tag set on update and deletes tags that nothing uses anymore', function () {
    $user = User::factory()->create();
    actingAs($user)->post(route('recipes.store'), taggedRecipe(['soep', 'brood', 'snel']));
    $recipe = Recipe::query()->sole();

    $this->put(route('recipes.update', $recipe), taggedRecipe(['brood', 'Winter']))->assertSessionHasNoErrors();

    expect(tagNames($recipe))->toBe(['brood', 'winter'])
        ->and(allTagNames())->toBe(['brood', 'winter']);
});

it('removes all tags when the update sends none', function () {
    $user = User::factory()->create();
    actingAs($user)->post(route('recipes.store'), taggedRecipe(['soep']));
    $recipe = Recipe::query()->sole();

    $this->put(route('recipes.update', $recipe), taggedRecipe([]))->assertSessionHasNoErrors();

    assertDatabaseCount('tags', 0);
    expect($recipe->tags()->count())->toBe(0);
});

it('keeps a tag that another recipe still uses', function () {
    $user = User::factory()->create();
    actingAs($user)->post(route('recipes.store'), taggedRecipe(['soep'], ['title' => 'Een']));
    $this->post(route('recipes.store'), taggedRecipe(['soep'], ['title' => 'Twee']));
    $first = Recipe::query()->where('title', 'Een')->sole();

    $this->put(route('recipes.update', $first), taggedRecipe([], ['title' => 'Een']));

    expect(allTagNames())->toBe(['soep']);
});

it('leaves the tags of another user alone when pruning', function () {
    $bob = User::factory()->create();
    Tag::factory()->for($bob)->create(['name' => 'ongebruikt']);
    $alice = User::factory()->create();

    actingAs($alice)->post(route('recipes.store'), taggedRecipe(['soep']));
    $recipe = Recipe::query()->sole();
    $this->put(route('recipes.update', $recipe), taggedRecipe([]));

    assertDatabaseHas('tags', ['user_id' => $bob->id, 'name' => 'ongebruikt']);
});

it('deletes tags that nothing uses anymore when the recipe is deleted, and keeps shared ones', function () {
    $user = User::factory()->create();
    actingAs($user)->post(route('recipes.store'), taggedRecipe(['soep', 'alleen hier'], ['title' => 'Een']));
    $this->post(route('recipes.store'), taggedRecipe(['soep'], ['title' => 'Twee']));
    $first = Recipe::query()->where('title', 'Een')->sole();

    $this->delete(route('recipes.destroy', $first))->assertRedirect(route('recipes.index'));

    expect(allTagNames())->toBe(['soep']);
});

it('answers 404 before validating when updating another user’s recipe with bad tags', function () {
    $recipe = Recipe::factory()->create();

    actingAs(User::factory()->create())->put(route('recipes.update', $recipe), taggedRecipe('nope'))
        ->assertNotFound();
});

it('does not touch the tags of another user’s recipe on update or delete', function () {
    $owner = User::factory()->create();
    $recipe = Recipe::factory()->for($owner)->create();
    $recipe->tags()->attach(Tag::factory()->for($owner)->create(['name' => 'soep']));

    actingAs(User::factory()->create());
    $this->put(route('recipes.update', $recipe), taggedRecipe([]))->assertNotFound();
    $this->delete(route('recipes.destroy', $recipe))->assertNotFound();

    expect(tagNames($recipe))->toBe(['soep']);
});

it('leaves no recipe behind when the tag save fails', function () {
    Tag::saving(fn () => throw new RuntimeException('boom'));

    try {
        actingAs(User::factory()->create())->withoutExceptionHandling()->post(route('recipes.store'), taggedRecipe(['soep']));
    } catch (RuntimeException) {
    } finally {
        Tag::flushEventListeners();
    }

    assertDatabaseCount('recipes', 0);
});

// Showing

it('shows a recipe with its tags', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    $recipe->tags()->attach([
        Tag::factory()->for($user)->create(['name' => 'soep'])->id,
        Tag::factory()->for($user)->create(['name' => 'brood'])->id,
    ]);

    actingAs($user)->get(route('recipes.show', $recipe))
        ->assertInertia(fn (Assert $page) => $page
            ->component('recipes/Show')
            ->has('recipe.tags', 2)
            ->where('recipe.tags.0.name', 'brood')
            ->where('recipe.tags.1.name', 'soep')
            ->missing('recipe.tags.0.user_id')
            ->missing('recipe.tags.0.pivot'));
});

it('hands the edit and create forms only the user’s own tags as suggestions', function () {
    $user = User::factory()->create();
    Tag::factory()->for($user)->create(['name' => 'soep']);
    Tag::factory()->for($user)->create(['name' => 'brood']);
    Tag::factory()->create(['name' => 'geheim']);
    $recipe = Recipe::factory()->for($user)->create();

    actingAs($user)->get(route('recipes.create'))
        ->assertInertia(fn (Assert $page) => $page->where('knownTags', ['brood', 'soep']));

    $this->get(route('recipes.edit', $recipe))
        ->assertInertia(fn (Assert $page) => $page
            ->where('knownTags', ['brood', 'soep'])
            ->has('recipe.tags', 0));
});
