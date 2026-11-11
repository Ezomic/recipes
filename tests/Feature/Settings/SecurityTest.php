<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_lists_passkeys_without_asking_for_a_password()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->where('canManagePasskeys', true)
                ->where('passkeys', [])
                ->missing('canManageTwoFactor')
                ->missing('passwordRules'),
            );
    }

    public function test_security_page_renders_without_passkeys_when_the_feature_is_disabled()
    {
        config(['fortify.features' => [Features::emailVerification()]]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManagePasskeys', false)
                ->where('passkeys', []),
            );
    }

    public function test_security_page_requires_login()
    {
        $this->get(route('security.edit'))->assertRedirect(route('login'));
    }

    public function test_the_password_and_two_factor_routes_are_gone()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/settings/password', ['password' => 'new-password'])->assertNotFound();
        $this->actingAs($user)->post('/user/two-factor-authentication')->assertNotFound();
    }
}
