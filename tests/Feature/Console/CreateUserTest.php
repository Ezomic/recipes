<?php

declare(strict_types=1);

use App\Models\User;

it('creates a verified user without asking for a password', function () {
    $this->artisan('user:create', ['name' => 'Robbin', 'email' => 'robbin@example.com'])
        ->expectsOutputToContain('robbin@example.com')
        ->assertSuccessful();

    $user = User::query()->where('email', 'robbin@example.com')->firstOrFail();

    expect($user->name)->toBe('Robbin')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->not->toBeNull()
        ->and(strlen((string) $user->password))->toBeGreaterThan(40);
});

it('gives the user a password nobody can sign in with', function () {
    $this->artisan('user:create', ['name' => 'Robbin', 'email' => 'robbin@example.com'])->assertSuccessful();

    $this->post(route('login.store'), ['email' => 'robbin@example.com', 'password' => 'password', 'code' => '123456'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
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
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('stores the email trimmed and lowercased', function () {
    $this->artisan('user:create', ['name' => 'Robbin', 'email' => 'Robbin@Example.COM'])
        ->assertSuccessful();

    expect(User::query()->firstOrFail()->email)->toBe('robbin@example.com');
});
