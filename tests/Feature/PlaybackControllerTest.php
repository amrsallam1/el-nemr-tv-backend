<?php

namespace Tests\Feature;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaybackControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_proxies_a_fresh_media_url_without_exposing_its_tls_to_android(): void
    {
        config()->set('services.content_worker.url', 'https://worker.example/');
        config()->set('services.content_worker.allowed_source_hosts', ['catalog.example']);
        config()->set('services.content_worker.allowed_media_host_suffixes', ['media.example']);
        Http::fake([
            'https://worker.example/*' => Http::response([
                'status' => 'success',
                'media_src' => 'https://media.example/fresh-token/video.mp4',
            ]),
            'https://media.example/*' => Http::response('video-bytes', 206, [
                'Content-Type' => 'video/mp4',
                'Content-Range' => 'bytes 0-10/100',
                'Accept-Ranges' => 'bytes',
            ]),
        ]);
        $media = Media::create([
            'type' => 'movie', 'title' => 'Authorized Movie', 'slug' => 'authorized-movie',
            'is_published' => true,
        ]);
        $stream = $media->streams()->create([
            'name' => 'El-Nemr Worker', 'url' => 'https://catalog.example/movie/123/movie',
            'source_url' => 'https://catalog.example/movie/123/movie', 'is_active' => true,
        ]);

        $this->withHeader('Range', 'bytes=0-10')
            ->get('/api/play/'.$stream->id)
            ->assertStatus(206)
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('Content-Range', 'bytes 0-10/100')
            ->assertStreamedContent('video-bytes');

        Http::assertSent(fn ($request) => $request->url() === 'https://media.example/fresh-token/video.mp4'
            && $request->hasHeader('Range', 'bytes=0-10'));
    }

    public function test_it_rejects_unapproved_source_hosts(): void
    {
        config()->set('services.content_worker.allowed_source_hosts', ['catalog.example']);
        $media = Media::create([
            'type' => 'movie', 'title' => 'Movie', 'slug' => 'movie', 'is_published' => true,
        ]);
        $stream = $media->streams()->create([
            'name' => 'El-Nemr Worker', 'url' => 'https://evil.example/movie',
            'source_url' => 'https://evil.example/movie', 'is_active' => true,
        ]);

        $this->get('/api/play/'.$stream->id)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_it_rejects_a_resolved_media_host_outside_the_allowlist(): void
    {
        config()->set('services.content_worker.url', 'https://worker.example/');
        config()->set('services.content_worker.allowed_source_hosts', ['catalog.example']);
        config()->set('services.content_worker.allowed_media_host_suffixes', ['media.example']);
        Http::fake([
            'https://worker.example/*' => Http::response([
                'status' => 'success',
                'media_src' => 'https://untrusted.example/video.mp4',
            ]),
        ]);
        $media = Media::create([
            'type' => 'movie', 'title' => 'Movie', 'slug' => 'movie-host-check', 'is_published' => true,
        ]);
        $stream = $media->streams()->create([
            'name' => 'El-Nemr Worker', 'url' => 'https://catalog.example/movie/321',
            'source_url' => 'https://catalog.example/movie/321', 'is_active' => true,
        ]);

        $this->get('/api/play/'.$stream->id)->assertForbidden();
    }
}
