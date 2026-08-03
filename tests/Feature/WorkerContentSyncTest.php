<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Services\WorkerContentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkerContentSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_a_movie_and_does_not_duplicate_it(): void
    {
        config()->set('services.content_worker.url', 'https://worker.example/');
        config()->set('services.content_worker.catalog_origin', 'https://catalog.example');
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return match ($query['action'] ?? null) {
                'genre' => Http::response([
                    'status' => 'success',
                    'data' => [[
                        'title' => 'Authorized Movie',
                        'href' => 'https://catalog.example/movie/123/authorized-movie',
                        'img' => 'https://images.example/movie.webp',
                    ]],
                ]),
                'series' => Http::response([
                    'status' => 'success', 'movie_title' => 'Authorized Movie',
                    'media_src' => 'https://media.example/movie.mp4', 'episodes' => [],
                ]),
                default => Http::response([], 404),
            };
        });

        $service = app(WorkerContentSyncService::class);
        $first = $service->sync('movies', 1, 10);
        $second = $service->sync('movies', 1, 10);

        $this->assertSame(1, $first['created']);
        $this->assertSame(1, $second['updated']);
        $this->assertDatabaseCount('media', 1);
        $this->assertDatabaseCount('streams', 1);
        $this->assertDatabaseHas('media', [
            'source' => 'content-worker', 'source_id' => '123',
            'type' => 'movie', 'is_published' => true,
        ]);
        $this->assertDatabaseHas('streams', [
            'source_url' => 'https://catalog.example/movie/123/authorized-movie',
            'name' => 'El-Nemr Worker',
        ]);
    }

    public function test_it_imports_series_episodes(): void
    {
        config()->set('services.content_worker.url', 'https://worker.example/');
        config()->set('services.content_worker.catalog_origin', 'https://catalog.example');
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            if (($query['action'] ?? null) === 'genre') {
                return Http::response(['status' => 'success', 'data' => [[
                    'title' => 'Authorized Series',
                    'href' => 'https://catalog.example/series/77/authorized-series',
                    'img' => 'https://images.example/series.webp',
                ]]]);
            }

            return Http::response([
                'status' => 'success', 'movie_title' => 'Authorized Series', 'media_src' => null,
                'episodes' => [
                    ['num' => 1, 'link' => 'https://catalog.example/episode/77/1'],
                    ['num' => 2, 'link' => 'https://catalog.example/episode/77/2'],
                ],
            ]);
        });

        app(WorkerContentSyncService::class)->sync('series', 1, 10);

        $this->assertDatabaseCount('media', 1);
        $this->assertDatabaseCount('seasons', 1);
        $this->assertDatabaseCount('episodes', 2);
        $this->assertDatabaseCount('streams', 2);
    }

    public function test_it_ignores_non_catalog_links_from_worker_results(): void
    {
        config()->set('services.content_worker.url', 'https://worker.example/');
        config()->set('services.content_worker.catalog_origin', 'https://catalog.example');
        Http::fake(Http::response(['status' => 'success', 'data' => [[
            'title' => 'Logo', 'href' => 'https://catalog.example/one',
            'img' => 'https://catalog.example/logo.svg',
        ]]]));

        $report = app(WorkerContentSyncService::class)->sync('movies', 1, 10);

        $this->assertSame(1, $report['skipped']);
        $this->assertDatabaseCount('media', 0);
    }
}
