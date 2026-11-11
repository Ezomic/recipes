<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Middleware\RequirePasswordInWorkflowMode;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Base mode has no password to confirm with. A user signed in through ID takes a
    // fresh trip through ID instead (WEB-31).
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])
        ->middleware(RequirePasswordInWorkflowMode::class)
        ->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::get('settings/appearance', fn () => Inertia::render('settings/Appearance'))->name('appearance.edit');

    // A personal access token is a full-access credential. There is no password to
    // confirm with in base mode, so only workflow mode asks again (through ID).
    Route::middleware(RequirePasswordInWorkflowMode::class)->group(function () {
        Route::get('settings/api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
        Route::post('settings/api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
        Route::delete('settings/api-tokens/{token}', [ApiTokenController::class, 'destroy'])
            ->whereNumber('token')
            ->name('api-tokens.destroy');
    });
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
