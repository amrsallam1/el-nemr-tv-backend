<?php

namespace Tests\Feature;

use App\Services\WorkerContentSyncService;
use Mockery\MockInterface;
use Tests\TestCase;

class InternalContentSyncControllerTest extends TestCase
{
    public function test_it_requires_the_private_sync_token(): void
    {
        config()->set('services.content_worker.sync_token', 'test-secret');

        $this->postJson('/api/internal/content-sync')->assertForbidden();
    }

    public function test_it_runs_a_bounded_sync_with_the_private_token(): void
    {
        config()->set('services.content_worker.sync_token', 'test-secret');
        $this->mock(WorkerContentSyncService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sync')->once()->with('movies', 1, 5, false)->andReturn([
                'type' => 'movies', 'created' => 2, 'updated' => 0, 'failed' => 0,
            ]);
        });

        $this->withToken('test-secret')->postJson('/api/internal/content-sync', [
            'type' => 'movies', 'pages' => 1, 'limit' => 5,
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('reports.0.created', 2);
    }
}
