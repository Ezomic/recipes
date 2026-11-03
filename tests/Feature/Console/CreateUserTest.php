<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a verified user who can sign in', function () {
    $this->artisan('user:create', ['name' => 'Robbin', 'email' => 'robbin@example.com'])
        ->expectsQuestion('Password', 'a-long-enough-secret')
        ->expectsOutputToContain('robbin@example.com')
        ->assertSuccessful();

    $user = User::query()->where('email', 'robbin@example.com')->firstOrFail();

    expect($user->name)->toBe('Robbin')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-long-enough-secret', $user->password ?? ''))->toBeTrue();

    $this->post(route('login.store'), ['email' => 'robbin@example.com', 'password' => 'a-long-enough-secret'])
        ->assertRedirect(route('recipes.index', absolute: false));
});

it('refuses a second user, since the app has exactly one login', function () {
    User::factory()->create();

    $this->artisan('user:create', ['name' => 'Second', 'email' => 'second@example.com'])
        ->expectsOutputToContain('already')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('refuses an invalid email', function () {
    $this->artisan('user:create', ['name' => 'Robbin', 'email' => 'not-an-email'])
        ->expectsQuestion('Password', 'a-long-enough-secret')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('refuses a weak password', function () {
    $this->artisan('user:create', ['name' => 'Robbin', 'email' => 'robbin@example.com'])
        ->expectsQuestion('Password', 'short')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});
