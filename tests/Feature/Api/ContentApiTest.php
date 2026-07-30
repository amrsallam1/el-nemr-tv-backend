<?php

namespace Tests\Feature\Api;

use App\Models\Genre;
use App\Models\Media;
use App\Models\Stream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_endpoint_only_returns_published_media_in_android_shape(): void
    {
        $genre = Genre::create(['name' => 'Action', 'slug' => 'action']);
        $published = Media::create([
            'type' => 'movie',
            'title' => 'Published Movie',
            'slug' => 'published-movie',
            'is_published' => true,
        ]);
        $published->genres()->attach($genre);
        Media::create([
            'type' => 'movie',
            'title' => 'Draft Movie',
            'slug' => 'draft-movie',
            'is_published' => false,
        ]);

        $this->getJson('/api/media/latestcontent/test')
            ->assertOk()
            ->assertJsonCount(1, 'latest')
            ->assertJsonPath('latest.0.id', (string) $published->id)
            ->assertJsonPath('latest.0.type', 'movie')
            ->assertJsonPath('latest.0.name', null)
            ->assertJsonPath('latest.0.genres.0.name', 'Action')
            ->assertJsonMissing(['title' => 'Draft Movie']);
    }

    public function test_series_and_live_types_are_translated_for_legacy_android_models(): void
    {
        Media::create(['type' => 'series', 'title' => 'Show', 'slug' => 'show', 'is_published' => true]);
        Media::create(['type' => 'live', 'title' => 'Channel', 'slug' => 'channel', 'is_published' => true]);

        $this->getJson('/api/series/recents/test')->assertJsonPath('recents.0.type', 'serie');
        $this->getJson('/api/livetv/latest/test')->assertJsonPath('livetv.0.type', 'streaming');
    }

    public function test_draft_media_detail_is_not_public(): void
    {
        $draft = Media::create([
            'type' => 'movie',
            'title' => 'Draft',
            'slug' => 'draft',
            'is_published' => false,
        ]);

        $this->getJson("/api/media/show/{$draft->id}/test")->assertNotFound();
    }

    public function test_movie_detail_matches_legacy_android_stream_shape(): void
    {
        $movie = Media::create([
            'type' => 'movie',
            'title' => 'Playable Movie',
            'slug' => 'playable-movie',
            'is_published' => true,
        ]);
        Stream::create([
            'media_id' => $movie->id,
            'name' => 'Main Server',
            'url' => 'https://example.com/movie.m3u8',
            'quality' => '1080P',
            'language' => 'English',
            'is_active' => true,
        ]);

        $this->getJson("/api/media/detail/{$movie->id}/test")
            ->assertOk()
            ->assertJsonPath('videos.0.server', 'Main Server')
            ->assertJsonPath('videos.0.link', 'https://example.com/movie.m3u8')
            ->assertJsonPath('videos.0.lang', 'English')
            ->assertJsonPath('videos.0.hls', 1);
    }

    public function test_free_app_returns_an_empty_legacy_plans_collection(): void
    {
        $this->getJson('/api/plans/plans/test')
            ->assertOk()
            ->assertExactJson(['plans' => []]);
    }

    public function test_android_search_finds_published_titles_and_hides_drafts(): void
    {
        $published = Media::create([
            'type' => 'movie',
            'title' => 'Spider Man Cairo',
            'slug' => 'spider-man-cairo',
            'tmdb_id' => '12345',
            'is_published' => true,
        ]);
        Media::create([
            'type' => 'movie',
            'title' => 'Spider Draft',
            'slug' => 'spider-draft',
            'is_published' => false,
        ]);

        $this->getJson('/api/search/spider/test')
            ->assertOk()
            ->assertJsonCount(1, 'search')
            ->assertJsonPath('search.0.id', (string) $published->id)
            ->assertJsonMissing(['title' => 'Spider Draft']);
    }

    public function test_legacy_home_sections_return_the_expected_keys(): void
    {
        Media::create([
            'type' => 'movie',
            'title' => 'Pinned Movie',
            'slug' => 'pinned-movie',
            'is_published' => true,
            'is_pinned' => true,
            'views' => 100,
        ]);

        $this->getJson('/api/media/pinnedcontent/test')->assertOk()->assertJsonCount(1, 'pinned');
        $this->getJson('/api/media/popularcontent/test')->assertOk()->assertJsonStructure(['popular']);
        $this->getJson('/api/media/topcontent/test')->assertOk()->assertJsonStructure(['top10']);
        $this->getJson('/api/genres/list/test')->assertOk()->assertJsonStructure(['categories', 'genres']);
    }
}
