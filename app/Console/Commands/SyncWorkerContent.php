<?php

namespace App\Console\Commands;

use App\Services\WorkerContentSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncWorkerContent extends Command
{
    protected $signature = 'elnemr:sync-worker
        {--type=all : movies, series, or all}
        {--pages=1 : Number of catalog pages}
        {--limit=50 : Maximum items per content type}
        {--dry-run : Inspect without changing the database}
        {--force-unlock : Release a stale sync lock}';

    protected $description = 'Synchronize authorized catalog data from the configured content worker';

    public function handle(WorkerContentSyncService $sync): int
    {
        $type = strtolower(trim((string) $this->option('type')));
        $pages = filter_var($this->option('pages'), FILTER_VALIDATE_INT);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if (! in_array($type, ['movies', 'series', 'all'], true) || ! is_int($pages) || ! is_int($limit)) {
            $this->error('Use --type=movies|series|all with valid integer --pages and --limit values.');
            return self::INVALID;
        }

        if ((bool) $this->option('force-unlock')) {
            Cache::lock('elnemr:sync-worker')->forceRelease();
        }
        $lock = Cache::lock('elnemr:sync-worker', (int) config('services.content_worker.lock_seconds', 3600));
        if (! $lock->get()) {
            $this->warn('Another worker sync is already running.');
            return self::SUCCESS;
        }

        try {
            $reports = [];
            foreach ($type === 'all' ? ['movies', 'series'] : [$type] as $currentType) {
                $this->info('Synchronizing '.$currentType.'...');
                $reports[] = $sync->sync(
                    $currentType, $pages, $limit, (bool) $this->option('dry-run'),
                    fn (string $message) => $this->line($message),
                );
            }

            $this->newLine();
            $this->table(['Type', 'Scanned', 'Created', 'Updated', 'Skipped', 'Failed'], array_map(fn (array $report) => [
                $report['type'], $report['scanned'], $report['created'], $report['updated'],
                $report['skipped'], $report['failed'],
            ], $reports));

            return collect($reports)->sum('failed') > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $error) {
            report($error);
            $this->error($error instanceof \RuntimeException ? $error->getMessage() : 'Worker sync failed. Check the server log.');
            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
