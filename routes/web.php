<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GenreController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ScraperController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'create'])
        ->middleware('cache.headers:no_store;no_cache;must_revalidate;max_age=0')
        ->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'store'])->name('admin.login.store');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('media-import', [ImportController::class, 'create'])->name('media.import');
    Route::post('media-import', [ImportController::class, 'store'])->name('media.import.store');
    Route::post('scraper/run', [ScraperController::class, 'run'])->name('scraper.run');
    Route::get('notifications', [NotificationController::class, 'create'])->name('notifications.create');
    Route::post('notifications', [NotificationController::class, 'store'])->name('notifications.store');
    Route::get('genres', [GenreController::class, 'index'])->name('genres.index');
    Route::post('genres', [GenreController::class, 'store'])->name('genres.store');
    Route::put('genres/{genre}', [GenreController::class, 'update'])->name('genres.update');
    Route::delete('genres/{genre}', [GenreController::class, 'destroy'])->name('genres.destroy');
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::resource('media', MediaController::class)
        ->parameters(['media' => 'media'])
        ->except(['show']);
    Route::get('media/{media}/catalog', [CatalogController::class, 'show'])->name('media.catalog');
    Route::post('media/{media}/seasons', [CatalogController::class, 'storeSeason'])->name('seasons.store');
    Route::delete('media/{media}/seasons/{season}', [CatalogController::class, 'destroySeason'])->name('seasons.destroy');
    Route::post('seasons/{season}/episodes', [CatalogController::class, 'storeEpisode'])->name('episodes.store');
    Route::delete('episodes/{episode}', [CatalogController::class, 'destroyEpisode'])->name('episodes.destroy');
    Route::post('media/{media}/streams', [CatalogController::class, 'storeMediaStream'])->name('media.streams.store');
    Route::post('episodes/{episode}/streams', [CatalogController::class, 'storeEpisodeStream'])->name('episodes.streams.store');
    Route::delete('streams/{stream}', [CatalogController::class, 'destroyStream'])->name('streams.destroy');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
