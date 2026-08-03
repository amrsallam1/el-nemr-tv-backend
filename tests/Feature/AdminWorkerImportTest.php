<?php

namespace Tests\Feature;

use App\Jobs\RunWorkerContentImport;
use App\Models\ContentImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminWorkerImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_and_queue_a_worker_import(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/worker-import')
            ->assertOk()->assertSee('الاستيراد التلقائي المتحكم به');

        $this->actingAs($admin)->post('/admin/worker-import', [
            'type' => 'movies',
            'language' => 'arabic',
            'years' => [2019, 2025, 2026],
            'limit' => 35,
            'pages' => 6,
        ])->assertRedirect(route('admin.worker-import.index'));

        $run = ContentImportRun::firstOrFail();
        $this->assertSame([2019, 2025, 2026], $run->options['years']);
        $this->assertSame('arabic', $run->options['language']);
        Queue::assertPushed(RunWorkerContentImport::class, fn ($job) => $job->runId === $run->id);
    }

    public function test_import_rejects_years_outside_the_manual_range(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/worker-import', [
            'type' => 'series', 'language' => 'all', 'years' => [2014], 'limit' => 10, 'pages' => 2,
        ])->assertSessionHasErrors('years.0');

        Queue::assertNothingPushed();
    }
}
