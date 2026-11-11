<?php

declare(strict_types=1);

use App\Models\User;

it('runs in Dutch by default', function (): void {
    expect(app()->getLocale())->toBe('nl')
        ->and(config('app.fallback_locale'))->toBe('nl');
});

it('validates framework rules in Dutch', function (): void {
    $this->actingAs(User::factory()->create())
        ->patch(route('profile.update'), ['name' => '', 'email' => 'geen-adres'])
        ->assertSessionHasErrors([
            'name' => 'Naam is verplicht.',
            'email' => 'E-mailadres moet een geldig e-mailadres zijn.',
        ]);
});

it('words the one-user guard in Dutch', function (): void {
    User::factory()->create();

    $this->artisan('user:create', ['name' => 'Anna', 'email' => 'anna@example.com'])
        ->expectsOutputToContain('Er bestaat al een gebruiker')
        ->assertFailed();
});
