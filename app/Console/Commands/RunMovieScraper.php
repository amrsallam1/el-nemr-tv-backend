<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class RunMovieScraper extends Command
{
    protected $signature = 'scraper:run {--path= : Absolute path to scraper.js} {--cwd= : Working directory for the scraper}';

    protected $description = 'Run the external movie scraper from the admin panel';

    public function handle(): int
    {
        $bundledScriptPath = base_path('scraper/scraper.js');
        $bundledWorkingDirectory = base_path('scraper');

        $scriptPath = $this->option('path') ?: (is_file($bundledScriptPath) ? $bundledScriptPath : config('services.scraper.path'));
        $workingDirectory = $this->option('cwd') ?: (is_dir($bundledWorkingDirectory) ? $bundledWorkingDirectory : config('services.scraper.cwd'));

        if (! $scriptPath || ! is_file($scriptPath)) {
            $this->error('Scraper script not found. Put scraper/scraper.js in the repo or set SCRAPER_SCRIPT_PATH.');
            return self::FAILURE;
        }

        $workingDirectory = $workingDirectory ?: dirname($scriptPath);

        if (! is_dir($workingDirectory)) {
            $this->error('Scraper working directory not found. Put scraper files in the repo or set SCRAPER_WORKDIR.');
            return self::FAILURE;
        }

        $process = Process::path($workingDirectory)->timeout(null)->run(['node', $scriptPath]);

        $this->line($process->output());

        if ($process->failed()) {
            $this->error($process->errorOutput());
            return self::FAILURE;
        }

        $this->info('Scraper finished successfully.');
        return self::SUCCESS;
    }
}
