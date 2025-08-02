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

    // Keyword management within mentions
    Route::post('/mentions/keywords', [MentionController::class, 'storeKeyword'])->name('mentions.store-keyword');
    Route::put('/mentions/keywords/{keyword}', [MentionController::class, 'updateKeyword'])->name('mentions.update-keyword');
    Route::delete('/mentions/keywords/{keyword}', [MentionController::class, 'destroyKeyword'])->name('mentions.destroy-keyword');

    // Reddit API integration
    Route::post('/mentions/search-subreddits', [MentionController::class, 'searchSubreddits'])->name('mentions.search-subreddits');

    // Monitoring
    Route::post('/mentions/start-monitoring', [MentionController::class, 'startMonitoring'])->name('mentions.start-monitoring');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
