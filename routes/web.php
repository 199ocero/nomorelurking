<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MentionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('/mentions', [MentionController::class, 'index'])->name('mentions');

    Route::get('/auth/reddit', [AuthController::class, 'redirectToReddit'])->name('reddit.auth');
    Route::get('/auth/reddit/callback', [AuthController::class, 'handleRedditCallback'])->name('reddit.callback');
    Route::post('/auth/reddit/disconnect', [AuthController::class, 'disconnectReddit'])->name('reddit.disconnect');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
