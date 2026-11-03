<?php

declare(strict_types=1);

use App\Actions\Recipes\SearchRecipes;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use App\Support\RecipeFilters;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;

/**
 * @param  list<string>  $tags
 * @param  list<string>  $ingredients
 * @param  array<string, mixed>  $attributes
 */
function stockRecipe(User $user, string $title, array $tags = [], array $ingredients = [], array $attributes = []): Recipe
{
    $recipe = Recipe::factory()->for($user)->create(['title' => $title, ...$attributes]);

    foreach ($tags as $name) {
        $tag = Tag::query()->firstOrCreate(['user_id' => $user->id, 'name' => $name]);
        $recipe->tags()->attach($tag);
    }

    foreach ($ingredients as $position => $name) {
        Ingredient::factory()->for($recipe)->create(['position' => $position, 'name' => $name]);
    }

    return $recipe;
}

/**
 * @return list<string>
 */
function listedTitles(string $query = ''): array
{
    /** @var list<string> */
    return collect(test()->get('/recepten'.($query === '' ? '' : '?'.$query))->assertOk()->viewData('page')['props']['recipes']['data'])
        ->pluck('title')
        ->sort()
        ->values()
        ->all();
}

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

// Search

it('finds recipes by a substring of the title, ignoring case', function () {
    stockRecipe($this->user, 'Romige Tomatensoep');
    stockRecipe($this->user, 'Brood');

    expect(listedTitles('q=tomaten'))->toBe(['Romige Tomatensoep'])
        ->and(listedTitles('q=ROMIGE'))->toBe(['Romige Tomatensoep'])
        ->and(listedTitles('q=soep'))->toBe(['Romige Tomatensoep']);
});

it('finds recipes by ingredient name', function () {
    stockRecipe($this->user, 'Soep', ingredients: ['Prei', 'Aardappel']);
    stockRecipe($this->user, 'Brood', ingredients: ['Bloem']);

    expect(listedTitles('q=aardap'))->toBe(['Soep']);
});

it('matches title or ingredient and lists a recipe once however many ingredients match', function () {
    stockRecipe($this->user, 'Kaassoep', ingredients: ['Kaas', 'Oude kaas', 'Kaaskorst']);
    stockRecipe($this->user, 'Salade', ingredients: ['Geitenkaas']);
    stockRecipe($this->user, 'Brood');

    expect(listedTitles('q=kaas'))->toBe(['Kaassoep', 'Salade']);
});

it('treats LIKE wildcards in the search as literal text', function (string $query, array $expected) {
    stockRecipe($this->user, '100% sap');
    stockRecipe($this->user, 'snake_case');
    stockRecipe($this->user, 'back\\slash');
    stockRecipe($this->user, '100x');
    stockRecipe($this->user, 'snakeXcase');
    stockRecipe($this->user, 'Gewoon', ingredients: ['50% kaas']);

    expect(listedTitles('q='.urlencode($query)))->toBe($expected);
})->with([
    'percent' => ['%', ['100% sap', 'Gewoon']],
    'underscore' => ['_', ['snake_case']],
    'backslash' => ['\\', ['back\\slash']],
    'percent and underscore' => ['%_', []],
    'percent in a word' => ['0% s', ['100% sap']],
    'underscore in a word' => ['e_c', ['snake_case']],
    'escaped percent sequence' => ['\\%', []],
]);

it('ignores an empty or whitespace-only search', function (string $query) {
    stockRecipe($this->user, 'Een');
    stockRecipe($this->user, 'Twee');

    expect(listedTitles('q='.urlencode($query)))->toBe(['Een', 'Twee']);
})->with(['empty' => [''], 'spaces' => ['   '], 'non-breaking space' => ["\u{00A0}"]]);

it('accepts a search of 100 characters and ignores one of 101', function () {
    stockRecipe($this->user, str_repeat('a', 100));
    stockRecipe($this->user, 'Ander');

    expect(listedTitles('q='.str_repeat('a', 100)))->toBe([str_repeat('a', 100)])
        ->and(listedTitles('q='.str_repeat('a', 101)))->toBe(['Ander', str_repeat('a', 100)]);
});

it('rejects a search that is not a string by ignoring it', function () {
    stockRecipe($this->user, 'Een');
    stockRecipe($this->user, 'Twee');

    expect(listedTitles('q[]=een'))->toBe(['Een', 'Twee'])
        ->and(listedTitles('q[a][b]=een'))->toBe(['Een', 'Twee']);
});

it('searches Unicode and emoji', function () {
    stockRecipe($this->user, 'Crème brûlée');
    stockRecipe($this->user, '🍕 pizza');
    stockRecipe($this->user, '日本のカレー');

    expect(listedTitles('q='.urlencode('brûlée')))->toBe(['Crème brûlée'])
        ->and(listedTitles('q='.urlencode('🍕')))->toBe(['🍕 pizza'])
        ->and(listedTitles('q='.urlencode('カレー')))->toBe(['日本のカレー']);
});

it('survives SQL-looking and malformed text in the search', function (string $query) {
    stockRecipe($this->user, 'Soep');

    expect(listedTitles('q='.urlencode($query)))->toBe([]);

    assertDatabaseCount('recipes', 1);
})->with([
    'quote' => ["' OR 1=1 --"],
    'stacked' => ["x'); DROP TABLE recipes; --"],
    'double quote' => ['" OR ""="'],
    'null byte' => ["a\0b"],
    'html' => ['<script>alert(1)</script>'],
]);

it('does not crash on invalid UTF-8 in the search', function () {
    stockRecipe($this->user, 'Soep');

    $this->get('/recepten?q=%C3%28')->assertOk();
});

// Tag filter

it('filters on one tag', function () {
    stockRecipe($this->user, 'Een', ['kamperen']);
    stockRecipe($this->user, 'Twee', ['vegetarisch']);
    stockRecipe($this->user, 'Drie');

    expect(listedTitles('tag[]=kamperen'))->toBe(['Een']);
});

it('requires all selected tags', function () {
    stockRecipe($this->user, 'Beide', ['kamperen', 'eenpans']);
    stockRecipe($this->user, 'Alleen kamperen', ['kamperen']);
    stockRecipe($this->user, 'Alleen eenpans', ['eenpans']);

    expect(listedTitles('tag[]=kamperen&tag[]=eenpans'))->toBe(['Beide']);
});

it('matches tags regardless of case and spacing in the URL', function () {
    stockRecipe($this->user, 'Een', ['kamperen']);

    expect(listedTitles('tag[]=Kamperen'))->toBe(['Een'])
        ->and(listedTitles('tag[]='.urlencode("  KAMPEREN\u{00A0}")))->toBe(['Een']);
});

it('treats a duplicated tag as one', function () {
    stockRecipe($this->user, 'Een', ['kamperen']);

    expect(listedTitles('tag[]=kamperen&tag[]=kamperen&tag[]=Kamperen'))->toBe(['Een']);
});

it('returns nothing for a tag that does not exist', function () {
    stockRecipe($this->user, 'Een', ['kamperen']);

    expect(listedTitles('tag[]=bestaatniet'))->toBe([])
        ->and(listedTitles('tag[]=kamperen&tag[]=bestaatniet'))->toBe([]);
});

it('ignores blank tag values', function () {
    stockRecipe($this->user, 'Een', ['kamperen']);
    stockRecipe($this->user, 'Twee');

    expect(listedTitles('tag[]=&tag[]=+'))->toBe(['Een', 'Twee'])
        ->and(listedTitles('tag[]=kamperen&tag[]='))->toBe(['Een']);
});

it('ignores a tag filter of the wrong shape', function (string $query) {
    stockRecipe($this->user, 'Een', ['kamperen']);
    stockRecipe($this->user, 'Twee');

    expect(listedTitles($query))->toBe(['Een', 'Twee']);
})->with([
    'a plain string' => ['tag=kamperen'],
    'nested arrays' => ['tag[][]=kamperen'],
    'keyed nested' => ['tag[a][b]=kamperen'],
    'too long a name' => ['tag[]='.str_repeat('a', 51)],
]);

it('ignores a huge tag array', function () {
    stockRecipe($this->user, 'Een', ['kamperen']);
    stockRecipe($this->user, 'Twee');

    $query = implode('&', array_map(fn (int $n): string => "tag[]=t{$n}", range(1, 200)));

    expect(listedTitles($query))->toBe(['Een', 'Twee']);
});

it('accepts up to ten tags in the filter', function () {
    $names = array_map(fn (int $n): string => "t{$n}", range(1, 10));
    stockRecipe($this->user, 'Alles', $names);
    stockRecipe($this->user, 'Bijna', array_slice($names, 0, 9));

    expect(listedTitles(implode('&', array_map(fn (string $n): string => "tag[]={$n}", $names))))->toBe(['Alles']);
});

// Max minutes

it('filters on a maximum preparation time, inclusive', function () {
    stockRecipe($this->user, 'Snel', attributes: ['preparation_minutes' => 15]);
    stockRecipe($this->user, 'Precies', attributes: ['preparation_minutes' => 30]);
    stockRecipe($this->user, 'Lang', attributes: ['preparation_minutes' => 31]);

    expect(listedTitles('max_minutes=30'))->toBe(['Precies', 'Snel'])
        ->and(listedTitles('max_minutes=14'))->toBe([])
        ->and(listedTitles('max_minutes=10080'))->toBe(['Lang', 'Precies', 'Snel']);
});

it('ignores a maximum time that is not a sensible whole number of minutes', function (string $value) {
    stockRecipe($this->user, 'Snel', attributes: ['preparation_minutes' => 5]);
    stockRecipe($this->user, 'Lang', attributes: ['preparation_minutes' => 500]);

    expect(listedTitles('max_minutes='.urlencode($value)))->toBe(['Lang', 'Snel']);
})->with(['zero' => ['0'], 'negative' => ['-5'], 'text' => ['abc'], 'decimal' => ['12.5'], 'huge' => ['99999999999999999999'], 'just over a week' => ['10081'], 'empty' => ['']]);

it('ignores a maximum time sent as an array', function () {
    stockRecipe($this->user, 'Snel', attributes: ['preparation_minutes' => 5]);
    stockRecipe($this->user, 'Lang', attributes: ['preparation_minutes' => 500]);

    expect(listedTitles('max_minutes[]=10'))->toBe(['Lang', 'Snel']);
});

// Houdbaar

it('filters on houdbaar', function (string $value) {
    stockRecipe($this->user, 'Houdbaar', attributes: ['houdbaar' => true]);
    stockRecipe($this->user, 'Vers', attributes: ['houdbaar' => false]);

    expect(listedTitles('houdbaar='.$value))->toBe(['Houdbaar']);
})->with(['1']);

it('does not filter when houdbaar is off, empty or not a boolean', function (string $value) {
    stockRecipe($this->user, 'Houdbaar', attributes: ['houdbaar' => true]);
    stockRecipe($this->user, 'Vers', attributes: ['houdbaar' => false]);

    expect(listedTitles('houdbaar='.urlencode($value)))->toBe(['Houdbaar', 'Vers']);
})->with(['0', 'false', '', 'true', 'ja', 'maybe', '2']);

it('ignores houdbaar sent as an array', function () {
    stockRecipe($this->user, 'Houdbaar', attributes: ['houdbaar' => true]);
    stockRecipe($this->user, 'Vers', attributes: ['houdbaar' => false]);

    expect(listedTitles('houdbaar[]=1'))->toBe(['Houdbaar', 'Vers']);
});

// Combining

it('combines all filters with AND', function () {
    stockRecipe($this->user, 'Kampeersoep', ['kamperen'], ['Prei'], ['preparation_minutes' => 20, 'houdbaar' => true]);
    stockRecipe($this->user, 'Kampeerbrood', ['kamperen'], ['Bloem'], ['preparation_minutes' => 20, 'houdbaar' => true]);
    stockRecipe($this->user, 'Lange soep', ['kamperen'], ['Prei'], ['preparation_minutes' => 90, 'houdbaar' => true]);
    stockRecipe($this->user, 'Verse soep', ['kamperen'], ['Prei'], ['preparation_minutes' => 20, 'houdbaar' => false]);
    stockRecipe($this->user, 'Thuissoep', [], ['Prei'], ['preparation_minutes' => 20, 'houdbaar' => true]);

    expect(listedTitles('q=prei&tag[]=kamperen&max_minutes=30&houdbaar=1'))->toBe(['Kampeersoep'])
        ->and(listedTitles('q=soep&tag[]=kamperen'))->toBe(['Kampeersoep', 'Lange soep', 'Verse soep']);
});

it('still applies the valid filters when another one is invalid', function () {
    stockRecipe($this->user, 'Soep', attributes: ['preparation_minutes' => 20]);
    stockRecipe($this->user, 'Brood', attributes: ['preparation_minutes' => 20]);

    expect(listedTitles('q=soep&max_minutes=abc&houdbaar=maybe'))->toBe(['Soep']);
});

it('ignores unknown query parameters', function () {
    stockRecipe($this->user, 'Soep');

    expect(listedTitles('user_id=999&sort=title&foo[]=bar'))->toBe(['Soep']);
});

// Props

it('hands the page the filters it applied, the user’s tags and whether any filter is active', function () {
    stockRecipe($this->user, 'Soep', ['kamperen']);
    Tag::factory()->create(['name' => 'van een ander']);

    $this->get('/recepten?q=soep&tag[]=Kamperen&max_minutes=30&houdbaar=1&max_minutes=30')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['q' => 'soep', 'tag' => ['kamperen'], 'max_minutes' => 30, 'houdbaar' => true, 'active' => true])
            ->where('knownTags', ['kamperen']));

    $this->get('/recepten')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['q' => null, 'tag' => [], 'max_minutes' => null, 'houdbaar' => false, 'active' => false]));
});

it('reports invalid filters as not applied', function () {
    $this->get('/recepten?q[]=x&tag=y&max_minutes=0&houdbaar=maybe')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['q' => null, 'tag' => [], 'max_minutes' => null, 'houdbaar' => false, 'active' => false]));
});

it('sends the tags of each listed recipe', function () {
    stockRecipe($this->user, 'Soep', ['kamperen', 'eenpans']);

    $this->get('/recepten')
        ->assertInertia(fn (Assert $page) => $page
            ->has('recipes.data.0.tags', 2)
            ->where('recipes.data.0.tags.0.name', 'eenpans')
            ->missing('recipes.data.0.tags.0.user_id')
            ->missing('recipes.data.0.tags.0.pivot'));
});

// Pagination

it('keeps the query string on the pagination links', function () {
    config(['pagination.per_page' => 2]);
    foreach (range(1, 5) as $n) {
        stockRecipe($this->user, "Soep {$n}", ['kamperen']);
    }
    stockRecipe($this->user, 'Brood');

    $this->get('/recepten?q=soep&tag[]=kamperen')
        ->assertInertia(fn (Assert $page) => $page
            ->where('recipes.total', 5)
            ->has('recipes.data', 2)
            ->where('recipes.next_page_url', fn (string $url) => str_contains(urldecode($url), 'q=soep') && str_contains(urldecode($url), 'tag[0]=kamperen') && str_contains($url, 'page=2')));
});

it('redirects a page beyond the last to the last page, keeping the filters', function () {
    config(['pagination.per_page' => 2]);
    foreach (range(1, 3) as $n) {
        stockRecipe($this->user, "Soep {$n}");
    }

    $response = $this->get('/recepten?q=soep&page=9');

    $response->assertRedirect();
    $location = (string) $response->headers->get('Location');
    expect($location)->toContain('q=soep')->toContain('page=2');
});

it('shows the first page for a page number that makes no sense', function (string $page) {
    config(['pagination.per_page' => 2]);
    foreach (range(1, 3) as $n) {
        stockRecipe($this->user, "Soep {$n}");
    }

    $this->get('/recepten?page='.urlencode($page))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('recipes.current_page', 1)->has('recipes.data', 2));
})->with(['zero' => ['0'], 'negative' => ['-3'], 'text' => ['abc'], 'decimal' => ['1.5'], 'huge' => ['9223372036854775807'], 'overflow' => ['99999999999999999999']]);

it('shows an empty list when nothing matches', function () {
    stockRecipe($this->user, 'Soep');

    $this->get('/recepten?q=niets&page=3')->assertRedirect('/recepten?q=niets&page=1');
    $this->get('/recepten?q=niets')->assertOk()->assertInertia(fn (Assert $page) => $page->has('recipes.data', 0)->where('filters.active', true));
});

it('sends a guest with a query string to the login page', function () {
    auth()->logout();

    $this->get('/recepten?q=soep')->assertRedirect(route('login'));
});

// User isolation

it('never shows another user’s recipes, ingredients, tags or counts', function () {
    $bob = User::factory()->create();
    stockRecipe($bob, 'Bobs soep', ['kamperen', 'geheim'], ['Prei'], ['preparation_minutes' => 5, 'houdbaar' => true]);
    stockRecipe($this->user, 'Eigen brood', ['kamperen'], ['Bloem'], ['preparation_minutes' => 5, 'houdbaar' => true]);

    expect(listedTitles())->toBe(['Eigen brood'])
        ->and(listedTitles('q=soep'))->toBe([])
        ->and(listedTitles('q=prei'))->toBe([])
        ->and(listedTitles('tag[]=geheim'))->toBe([])
        ->and(listedTitles('tag[]=kamperen'))->toBe(['Eigen brood'])
        ->and(listedTitles('max_minutes=10&houdbaar=1'))->toBe(['Eigen brood']);

    $this->get('/recepten')
        ->assertInertia(fn (Assert $page) => $page
            ->where('recipes.total', 1)
            ->where('knownTags', ['kamperen']));
});

it('does not let a tag id or user id in the query reach another user’s data', function () {
    $bob = User::factory()->create();
    $recipe = stockRecipe($bob, 'Bobs soep', ['geheim']);
    $tag = $recipe->tags()->sole();

    expect(listedTitles("tag[]={$tag->id}&user_id={$bob->id}&tag_id={$tag->id}"))->toBe([]);
});

// Queries

it('loads a page of recipes in a constant number of queries', function () {
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->get('/recepten?q=a&tag[]=t1&max_minutes=500')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    stockRecipe($this->user, 'a1', ['t1', 't2'], ['aa']);
    $few = $countQueries();

    foreach (range(2, 12) as $n) {
        stockRecipe($this->user, "a{$n}", ['t1', 't2', "x{$n}"], ['aa', 'ab']);
    }

    expect($countQueries())->toBe($few);
});

// The seam

it('searches through SearchRecipes with a filter object', function () {
    stockRecipe($this->user, 'Soep', ['kamperen']);
    stockRecipe($this->user, 'Brood');
    stockRecipe(User::factory()->create(), 'Soep van Bob', ['kamperen']);

    $result = app(SearchRecipes::class)->handle(
        $this->user,
        new RecipeFilters(search: 'soep', tags: ['kamperen'], maxMinutes: null, houdbaar: false, page: 1),
    );

    expect($result->pluck('title')->all())->toBe(['Soep'])
        ->and($result->first()?->relationLoaded('tags'))->toBeTrue();
});

it('normalises tag names and drops blanks and duplicates in the filter object', function () {
    $filters = new RecipeFilters(search: '  Soep ', tags: ['Kamperen', ' kamperen', '', "\u{00A0}"], maxMinutes: null, houdbaar: false, page: 1);

    expect($filters->tags)->toBe(['kamperen'])
        ->and($filters->search)->toBe('Soep')
        ->and($filters->isActive())->toBeTrue()
        ->and((new RecipeFilters(search: ' ', tags: [], maxMinutes: null, houdbaar: false, page: 1))->search)->toBeNull();
});
