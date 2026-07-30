<?php

use App\Http\Controllers\Api\Admin\EpisodeController as AdminEpisodeController;
use App\Http\Controllers\Api\Admin\GenreController as AdminGenreController;
use App\Http\Controllers\Api\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Api\Admin\SeasonController as AdminSeasonController;
use App\Http\Controllers\Api\Admin\StreamController as AdminStreamController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\WatchProgressController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => [
    'status' => 'ok',
    'service' => config('app.name'),
    'time' => now()->toIso8601String(),
]);

// The code parameter is retained for compatibility with the existing Android app.
Route::get('/settings/{code}', SettingsController::class)
    ->where('code', '[A-Za-z0-9._-]+');

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:20,1');

Route::middleware('token.auth')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/user/logout', [AuthController::class, 'logout']);

    Route::post('/{type}/addtofav/{media}', [FavoriteController::class, 'store'])
        ->where('type', 'movie|serie|anime|streaming');
    Route::get('/{type}/isMovieFavorite/{media}', [FavoriteController::class, 'show'])
        ->where('type', 'movie|serie|anime|streaming');
    Route::delete('/{type}/removefromfav/{media}', [FavoriteController::class, 'destroy'])
        ->where('type', 'movie|serie|anime|streaming');

    Route::post('/movies/sendResume/{code}', [WatchProgressController::class, 'store']);
    Route::get('/movies/resume/show/{id}/{code}', [WatchProgressController::class, 'show']);
});

Route::get('/media/latestcontent/{code}', [ContentController::class, 'latest']);
Route::get('/media/featuredcontent/{code}', [ContentController::class, 'featured']);
Route::get('/media/recommendedcontent/{code}', [ContentController::class, 'recommended']);
Route::get('/media/trendingcontent/{code}', [ContentController::class, 'trending']);
Route::get('/media/thisweekcontent/{code}', [ContentController::class, 'thisWeek']);
Route::get('/media/choosedcontent/{code}', [ContentController::class, 'chosen']);
Route::get('/movies/latest/{code}', [ContentController::class, 'movies']);
Route::get('/series/recents/{code}', [ContentController::class, 'series']);
Route::get('/animes/recents/{code}', [ContentController::class, 'animes']);
Route::get('/livetv/latest/{code}', [ContentController::class, 'live']);
Route::get('/media/show/{media}/{code}', [ContentController::class, 'show']);
Route::get('/media/detail/{media}/{code}', [ContentController::class, 'show']);
Route::get('/plans/plans/{code}', fn () => response()->json(['plans' => []]));
Route::get('/search/{query}/{code}', SearchController::class)
    ->where('query', '[^/]+');

Route::get('/media/pinnedcontent/{code}', [ContentController::class, 'pinned']);
Route::get('/media/popularcontent/{code}', [ContentController::class, 'popular']);
Route::get('/media/topcontent/{code}', [ContentController::class, 'top']);
Route::get('/media/previewscontent/{code}', [ContentController::class, 'previews']);
Route::get('/media/suggestedcontent/{code}', [ContentController::class, 'suggested']);
Route::get('/media/popularCasters/{code}', [ContentController::class, 'popularCasters']);
Route::get('/series/popular/{code}', [ContentController::class, 'popularSeries']);
Route::get('/series/recentscontent/{code}', [ContentController::class, 'series']);
Route::get('/series/newEpisodescontent/{code}', [ContentController::class, 'latestEpisodes']);
Route::get('/animes/newEpisodescontent/{code}', [ContentController::class, 'latestEpisodes']);
Route::get('/genres/list/{code}', [ContentController::class, 'genres']);

Route::prefix('admin')->middleware(['token.auth', 'admin'])->group(function () {
    Route::apiResource('media', AdminMediaController::class)->parameters(['media' => 'media']);
    Route::apiResource('genres', AdminGenreController::class)->except('show');
    Route::get('media/{media}/seasons', [AdminSeasonController::class, 'index']);
    Route::post('media/{media}/seasons', [AdminSeasonController::class, 'store']);
    Route::put('media/{media}/seasons/{season}', [AdminSeasonController::class, 'update']);
    Route::delete('media/{media}/seasons/{season}', [AdminSeasonController::class, 'destroy']);
    Route::get('seasons/{season}/episodes', [AdminEpisodeController::class, 'index']);
    Route::post('seasons/{season}/episodes', [AdminEpisodeController::class, 'store']);
    Route::put('seasons/{season}/episodes/{episode}', [AdminEpisodeController::class, 'update']);
    Route::delete('seasons/{season}/episodes/{episode}', [AdminEpisodeController::class, 'destroy']);
    Route::post('media/{media}/streams', [AdminStreamController::class, 'storeForMedia']);
    Route::post('episodes/{episode}/streams', [AdminStreamController::class, 'storeForEpisode']);
    Route::put('streams/{stream}', [AdminStreamController::class, 'update']);
    Route::delete('streams/{stream}', [AdminStreamController::class, 'destroy']);
});
