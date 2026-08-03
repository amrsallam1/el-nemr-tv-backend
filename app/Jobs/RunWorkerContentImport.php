<?php

namespace App\Jobs;

use App\Models\ContentImportRun;
use App\Services\WorkerContentSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunWorkerContentImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;
    public int $tries = 1;

    public function __construct(public int $runId) {}

    public function handle(WorkerContentSyncService $sync): void
    {
        $run = ContentImportRun::findOrFail($this->runId);
        $run->update(['status' => 'running', 'started_at' => now(), 'error' => null]);
        $options = $run->options;
        $types = $options['type'] === 'all' ? ['movies', 'series'] : [$options['type']];
        $reports = [];

        try {
            foreach ($types as $type) {
                $reports[] = $sync->sync(
                    $type,
                    (int) $options['pages'],
                    (int) $options['limit'],
                    false,
                    null,
                    array_map('intval', $options['years']),
                    (string) $options['language'],
                    true,
                );
                $run->update(['report' => ['reports' => $reports]]);
            }
            $run->update(['status' => 'completed', 'report' => ['reports' => $reports], 'finished_at' => now()]);
        } catch (Throwable $error) {
            report($error);
            $run->update([
                'status' => 'failed',
                'report' => ['reports' => $reports],
                'error' => $error->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }
}
