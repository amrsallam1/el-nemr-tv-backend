<?php

namespace App\Console\Commands;

use App\Services\PopularMovieSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncPopularMovies extends Command
{
    protected $signature = 'movies:sync-popular
        {--limit= : Number of new movies to add}
        {--dry-run : Check what would be added without changing the database}
        {--no-csv : Do not write the CSV backup}';

    protected $description = 'Add new popular TMDB movies and their first responding stream URL';

    public function handle(PopularMovieSyncService $sync): int
    {
        $limit = $this->option('limit') !== null
            ? filter_var($this->option('limit'), FILTER_VALIDATE_INT)
            : (int) config('services.movie_sync.max_movies', 5);

        if (! is_int($limit) || $limit < 1 || $limit > (int) config('services.movie_sync.max_allowed_movies', 500)) {
            $this->error('The limit must be a valid positive integer within the configured maximum.');

            return self::INVALID;
        }

        $lock = Cache::lock('movies:sync-popular', (int) config('services.movie_sync.lock_seconds', 21600));
        if (! $lock->get()) {
            $this->warn('Another movie sync is already running.');

            return self::SUCCESS;
        }

        try {
            $report = $sync->sync(
                $limit,
                (bool) $this->option('dry-run'),
                ! (bool) $this->option('no-csv'),
                fn (string $message) => $this->line($message),
            );

            $this->newLine();
            $this->table(['Metric', 'Count'], [
                ['Requested new movies', $report['requested']],
                [$report['dry_run'] ? 'Would add' : 'Added', $report['created']],
                ['Already existed', $report['existing']],
                ['No responding stream', $report['without_stream']],
                ['Adult skipped', $report['adult_skipped']],
                ['Failed', $report['failed']],
                ['TMDB pages scanned', $report['pages_scanned']],
            ]);
            if (isset($report['csv_path'])) {
                $this->info('CSV backup: '.$report['csv_path']);
            }

            return $report['failed'] > 0 && $report['created'] === 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $error) {
            report($error);
            $this->error($error instanceof \RuntimeException ? $error->getMessage() : 'Movie sync failed. Check the server log for details.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
