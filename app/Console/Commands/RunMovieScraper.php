<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunMovieScraper extends Command
{
    protected $signature = 'scraper:run {--limit= : Number of new movies to add} {--dry-run : Do not change the database}';

    protected $description = 'Backward-compatible alias for movies:sync-popular';

    public function handle(): int
    {
        $arguments = [];
        if ($this->option('limit') !== null) {
            $arguments['--limit'] = $this->option('limit');
        }
        if ($this->option('dry-run')) {
            $arguments['--dry-run'] = true;
        }

        return $this->call('movies:sync-popular', $arguments);
    }
}
