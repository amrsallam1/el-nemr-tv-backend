<?php

namespace Tests\Feature;

use App\Models\Media;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncPopularMoviesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.tmdb.key', 'test-key');
        config()->set('services.movie_sync.max_movies', 2);
        config()->set('services.movie_sync.max_pages', 3);
        config()->set('services.movie_sync.require_stream', true);
        config()->set('services.movie_sync.csv_path', 'movie-sync/test.csv');
        config()->set('services.movie_sync.stream_sources', [
            'https://stream-one.test/embed/{tmdb_id}',
            'https://stream-two.test/embed/{tmdb_id}',
        ]);
        Storage::fake('local');
    }

    public function test_it_skips_existing_movies_and_keeps_paging_until_the_limit_is_met(): void
    {
        Media::create([
            'type' => 'movie',
            'title' => 'Existing',
            'slug' => 'existing-1',
            'tmdb_id' => '1',
            'is_published' => true,
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'api.themoviedb.org')) {
                $page = (int) $request['page'];

                return Http::response(['results' => $page === 1
                    ? array_merge([
                        $this->movie(1, 'Existing'),
                        $this->movie(2, 'New Two'),
                    ], array_map(fn ($id) => $this->movie($id, 'Existing '.$id), range(10, 27)))
                    : [$this->movie(3, 'New Three')],
                ], 200);
            }

            if (str_contains($request->url(), 'stream-one.test')) {
                return Http::response('', 500);
            }

            return Http::response('', 200);
        });

        foreach (range(10, 27) as $id) {
            Media::create([
                'type' => 'movie',
                'title' => 'Existing '.$id,
                'slug' => 'existing-'.$id,
                'tmdb_id' => (string) $id,
                'is_published' => true,
            ]);
        }

        $this->artisan('movies:sync-popular', ['--limit' => 2])->assertSuccessful();

        $this->assertDatabaseHas('media', ['type' => 'movie', 'tmdb_id' => '2', 'title' => 'New Two']);
        $this->assertDatabaseHas('media', ['type' => 'movie', 'tmdb_id' => '3', 'title' => 'New Three']);
        $this->assertDatabaseCount('streams', 2);
        Storage::disk('local')->assertExists('movie-sync/test.csv');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.themoviedb.org') && (int) $request['page'] === 2);
    }

    public function test_dry_run_does_not_change_the_database_or_write_csv(): void
    {
        Http::fake([
            'https://api.themoviedb.org/*' => Http::response(['results' => [$this->movie(9, 'Preview')]], 200),
            '*' => Http::response('', 200),
        ]);

        $this->artisan('movies:sync-popular', ['--limit' => 1, '--dry-run' => true])
            ->expectsOutputToContain('Would add: Preview')
            ->assertSuccessful();

        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('streams', 0);
        Storage::disk('local')->assertMissing('movie-sync/test.csv');
    }

    public function test_database_rejects_duplicate_tmdb_identity_for_the_same_type(): void
    {
        $payload = [
            'type' => 'movie',
            'title' => 'One',
            'slug' => 'one-55',
            'tmdb_id' => '55',
            'is_published' => true,
        ];
        Media::create($payload);

        $this->expectException(QueryException::class);
        Media::create([...$payload, 'title' => 'Duplicate', 'slug' => 'duplicate-55']);
    }

    public function test_repeating_the_sync_does_not_add_the_same_movie_twice(): void
    {
        Http::fake([
            'https://api.themoviedb.org/*' => Http::response(['results' => [$this->movie(77, 'Only Once')]], 200),
            '*' => Http::response('', 200),
        ]);

        $this->artisan('movies:sync-popular', ['--limit' => 1])->assertSuccessful();
        $this->artisan('movies:sync-popular', ['--limit' => 1])->assertSuccessful();

        $this->assertDatabaseCount('media', 1);
        $this->assertDatabaseCount('streams', 1);
        $this->assertDatabaseHas('media', ['type' => 'movie', 'tmdb_id' => '77']);
    }

    public function test_soft_deleted_tmdb_identity_is_not_recreated(): void
    {
        $media = Media::create([
            'type' => 'movie',
            'title' => 'Deleted by admin',
            'slug' => 'deleted-88',
            'tmdb_id' => '88',
            'is_published' => true,
        ]);
        $media->delete();

        Http::fake([
            'https://api.themoviedb.org/*' => Http::response(['results' => [$this->movie(88, 'Deleted by admin')]], 200),
        ]);

        $this->artisan('movies:sync-popular', ['--limit' => 1])->assertSuccessful();

        $this->assertDatabaseCount('media', 1);
        $this->assertSoftDeleted('media', ['tmdb_id' => '88']);
        $this->assertDatabaseCount('streams', 0);
    }

    public function test_csv_neutralizes_spreadsheet_formulas(): void
    {
        Http::fake([
            'https://api.themoviedb.org/*' => Http::response([
                'results' => [$this->movie(99, '=HYPERLINK("https://bad.test")')],
            ], 200),
            '*' => Http::response('', 200),
        ]);

        $this->artisan('movies:sync-popular', ['--limit' => 1])->assertSuccessful();

        $csv = Storage::disk('local')->get('movie-sync/test.csv');
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    /** @return array<string, mixed> */
    private function movie(int $id, string $title): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'original_title' => $title,
            'overview' => 'Overview',
            'release_date' => '2026-07-30',
            'poster_path' => '/poster.jpg',
            'backdrop_path' => '/backdrop.jpg',
            'original_language' => 'ar',
            'vote_average' => 8.2,
            'vote_count' => 100,
            'popularity' => 50,
        ];
    }
}
