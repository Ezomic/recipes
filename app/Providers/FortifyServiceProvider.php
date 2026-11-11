<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Workflow\ConfirmIdentityThroughId;
use App\Http\Controllers\Auth\LoginCodeController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'email' => $request->session()->get(LoginCodeController::SESSION_EMAIL),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::confirmPasswordView(fn (Request $request) => config('workflow.enabled')
            ? app(ConfirmIdentityThroughId::class)->handle($request)
            : abort(404));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login-code-verify', fn (Request $request): Limit => Limit::perMinute(10)->by('login-verify:'.$request->ip()));

        RateLimiter::for('passkeys', function (Request $request) {
            $identifier = $request->string('credential.id')->value() ?: $request->session()->getId();

            return Limit::perMinute(10)->by($identifier.'|'.$request->ip());
        });
    }
}
