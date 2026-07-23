<?php

namespace Tests\Feature\Api;

use App\Models\AccessToken;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserLibraryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_favorites_with_legacy_routes(): void
    {
        [$plain, $user] = $this->userWithToken();
        $movie = Media::create([
            'type' => 'movie', 'title' => 'Movie', 'slug' => 'movie', 'is_published' => true,
        ]);

        $this->withToken($plain)->postJson("/api/movie/addtofav/{$movie->id}")
            ->assertOk()->assertJsonPath('status', 1);
        $this->withToken($plain)->getJson("/api/movie/isMovieFavorite/{$movie->id}")
            ->assertOk()->assertJsonPath('status', 1);
        $this->assertTrue($user->favorites()->whereKey($movie->id)->exists());

        $this->withToken($plain)->deleteJson("/api/movie/removefromfav/{$movie->id}")
            ->assertOk()->assertJsonPath('status', 0);
    }

    public function test_user_can_save_and_read_watch_progress(): void
    {
        [$plain] = $this->userWithToken();
        $movie = Media::create([
            'type' => 'movie',
            'title' => 'Movie',
            'slug' => 'progress-movie',
            'tmdb_id' => '1234',
            'is_published' => true,
        ]);

        $this->withToken($plain)->postJson('/api/movies/sendResume/test', [
            'user_resume_id' => 999,
            'tmdb' => '1234',
            'resumeWindow' => 2,
            'resumePosition' => 120,
            'movieDuration' => 7200,
            'deviceId' => 'phone',
        ])->assertOk()->assertJsonPath('body.resumePosition', 120);

        $this->withToken($plain)->getJson("/api/movies/resume/show/{$movie->tmdb_id}/test")
            ->assertOk()
            ->assertJsonPath('resumePosition', 120)
            ->assertJsonPath('movieDuration', 7200);
    }

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $plain = Str::random(80);
        AccessToken::create([
            'user_id' => $user->id,
            'name' => 'android',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHour(),
        ]);

        return [$plain, $user];
    }
}
