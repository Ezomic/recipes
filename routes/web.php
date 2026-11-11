<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginCodeController;
use App\Http\Controllers\Auth\LoginLinkController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\RecipePhotoController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'recipes.index' : 'login'))->name('home');

Route::get('health', HealthController::class)->name('health');

// POST /login replaces Fortify's password login, which registers the same name and URI
// earlier; the later route wins, so no password ever reaches the guard.
Route::middleware('guest')->group(function () {
    Route::post('login/code', [LoginCodeController::class, 'send'])->name('login.code.send');
    Route::post('login', [LoginCodeController::class, 'verify'])
        ->middleware('throttle:login-code-verify')
        ->name('login.store');
});

Route::get('login/link/{token}', LoginLinkController::class)
    ->middleware('signed')
    ->name('login.link');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('recepten/{recipe}/foto', RecipePhotoController::class)->name('recipes.photo');
    Route::resource('recepten', RecipeController::class)
        ->parameters(['recepten' => 'recipe'])
        ->names('recipes');

    Route::resource('notes', NoteController::class)->except('show');
});

require __DIR__.'/settings.php';
