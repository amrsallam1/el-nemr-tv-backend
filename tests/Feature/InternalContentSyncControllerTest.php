<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Services\WorkerContentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class InternalContentSyncControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_the_private_sync_token(): void
    {
        config()->set('services.content_worker.sync_token', 'test-secret');

        $this->postJson('/api/internal/content-sync')->assertForbidden();
    }

    public function test_it_runs_a_bounded_sync_with_the_private_token(): void
    {
        config()->set('services.content_worker.sync_token', 'test-secret');
        $this->mock(WorkerContentSyncService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sync')->once()->with('movies', 1, 5, false, null, [2025, 2026])->andReturn([
                'type' => 'movies', 'created' => 2, 'updated' => 0, 'failed' => 0,
            ]);
        });

        $this->withToken('test-secret')->postJson('/api/internal/content-sync', [
            'type' => 'movies', 'pages' => 1, 'limit' => 5, 'years' => [2025, 2026],
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('reports.0.created', 2);
    }

    public function test_it_only_resets_content_with_token_and_explicit_confirmation(): void
    {
        config()->set('services.content_worker.sync_token', 'test-secret');
        Media::create(['type' => 'movie', 'title' => 'Old', 'slug' => 'old', 'is_published' => true]);

        $this->withToken('test-secret')->postJson('/api/internal/content-reset', [
            'confirmation' => 'wrong',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('media', 1);

        $this->withToken('test-secret')->postJson('/api/internal/content-reset', [
            'confirmation' => 'DELETE ALL MEDIA',
        ])->assertOk()->assertJsonPath('deleted_media', 1);
        $this->assertDatabaseCount('media', 0);
    }
}
