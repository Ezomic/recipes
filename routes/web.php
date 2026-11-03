<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'recipes.index' : 'login'))->name('home');

Route::get('health', HealthController::class)->name('health');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('recepten', [RecipeController::class, 'index'])->name('recipes.index');

    Route::resource('notes', NoteController::class)->except('show');
});

require __DIR__.'/settings.php';
