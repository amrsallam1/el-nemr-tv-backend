<?php

namespace Tests\Feature\Api;

use App\Models\AccessToken;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_build_series_season_episode_and_stream_tree(): void
    {
        $token = $this->adminToken();
        $series = Media::create(['type' => 'series', 'title' => 'Series', 'slug' => 'series']);

        $season = $this->withToken($token)->postJson("/api/admin/media/{$series->id}/seasons", [
            'season_number' => 1, 'name' => 'Season 1',
        ])->assertCreated();

        $episode = $this->withToken($token)->postJson(
            '/api/admin/seasons/'.$season->json('id').'/episodes',
            ['episode_number' => 1, 'name' => 'Pilot'],
        )->assertCreated();

        $this->withToken($token)->postJson(
            '/api/admin/episodes/'.$episode->json('id').'/streams',
            ['name' => 'Main', 'url' => 'https://video.example/test.m3u8', 'quality' => '1080p'],
        )->assertCreated()->assertJsonPath('quality', '1080p');

        $this->assertDatabaseCount('seasons', 1);
        $this->assertDatabaseCount('episodes', 1);
        $this->assertDatabaseCount('streams', 1);
    }

    public function test_movie_can_have_direct_stream_but_cannot_have_seasons(): void
    {
        $token = $this->adminToken();
        $movie = Media::create(['type' => 'movie', 'title' => 'Movie', 'slug' => 'movie']);

        $this->withToken($token)->postJson("/api/admin/media/{$movie->id}/streams", [
            'url' => 'https://video.example/movie.mp4',
        ])->assertCreated();

        $this->withToken($token)->postJson("/api/admin/media/{$movie->id}/seasons", [
            'season_number' => 1,
        ])->assertUnprocessable();
    }

    public function test_episode_from_another_season_cannot_be_modified_through_wrong_parent(): void
    {
        $token = $this->adminToken();
        $series = Media::create(['type' => 'series', 'title' => 'Series', 'slug' => 'tree-series']);
        $first = $series->seasons()->create(['season_number' => 1]);
        $second = $series->seasons()->create(['season_number' => 2]);
        $episode = $first->episodes()->create(['episode_number' => 1, 'name' => 'Episode']);

        $this->withToken($token)->putJson("/api/admin/seasons/{$second->id}/episodes/{$episode->id}", [
            'name' => 'Wrong',
        ])->assertNotFound();
    }

    private function adminToken(): string
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin'])->save();
        $plain = Str::random(80);
        AccessToken::create([
            'user_id' => $user->id,
            'name' => 'android',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHour(),
        ]);

        return $plain;
    }
}
