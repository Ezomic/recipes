<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('sends a guest from the recipe list to the login page', function () {
    $this->get(route('recipes.index'))->assertRedirect(route('login'));
});

it('shows a signed-in user the recipe list', function () {
    actingAs(User::factory()->create());

    $this->get(route('recipes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('recipes/Index'));
});

it('sends a guest from the front door to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('sends a signed-in user from the front door to the recipe list', function () {
    actingAs(User::factory()->create());

    $this->get('/')->assertRedirect(route('recipes.index'));
});

it('lands a user on the recipe list after logging in with a code', function () {
    $user = User::factory()->create();
    fakeZero();

    requestLoginCode($user->email);

    $this->post(route('login.store'), ['email' => $user->email, 'code' => sentLoginCode()])
        ->assertRedirect(route('recipes.index', absolute: false));
});

it('has no registration route', function () {
    expect(Route::has('register'))->toBeFalse();

    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::query()->count())->toBe(0);
});
