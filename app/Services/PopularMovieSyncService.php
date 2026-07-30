<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PopularMovieSyncService
{
    /**
     * Restore streams for specific movies that already exist in the catalog.
     *
     * @param  array<int, string|int>  $tmdbIds
     * @param  callable(string): void|null  $logger
     * @return array{requested: int, restored: int, already_had_stream: int, not_found: int, without_stream: int, failed: int}
     */
    public function backfill(array $tmdbIds, ?callable $logger = null): array
    {
        $settings = config('services.movie_sync', []);
        $ids = collect($tmdbIds)
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '' && ctype_digit($id))
            ->unique()
            ->values();
        $report = ['requested' => $ids->count(), 'restored' => 0, 'already_had_stream' => 0, 'not_found' => 0, 'without_stream' => 0, 'failed' => 0];

        foreach ($ids as $tmdbId) {
            try {
                $media = Media::query()->where('type', 'movie')->where('tmdb_id', $tmdbId)->first();
                if (! $media) {
                    $report['not_found']++;
                    continue;
                }
                if ($media->streams()->where('is_active', true)->exists()) {
                    $report['already_had_stream']++;
                    continue;
                }

                $streamUrl = $this->findStream($tmdbId, $settings, $logger);
                if ($streamUrl === null) {
                    $report['without_stream']++;
                    continue;
                }

                $restored = DB::transaction(function () use ($media, $streamUrl) {
                    $locked = Media::query()->lockForUpdate()->find($media->id);
                    if (! $locked || $locked->streams()->where('is_active', true)->exists()) {
                        return false;
                    }
                    $locked->streams()->create([
                        'name' => 'Server 1', 'url' => $streamUrl, 'embed' => true,
                        'quality' => null, 'language' => null, 'is_active' => true, 'sort_order' => 0,
                    ]);

                    return true;
                }, 3);

                $restored ? $report['restored']++ : $report['already_had_stream']++;
                if ($restored && $logger) {
                    $logger('Restored stream: '.$media->title.' (TMDB '.$tmdbId.')');
                }
            } catch (\Throwable $error) {
                report($error);
                $report['failed']++;
                if ($logger) {
                    $logger("Failed TMDB {$tmdbId}: ".$this->safeError($error));
                }
            }
        }

        return $report;
    }

    /**
     * Import new popular movies and return a machine-readable run report.
     *
     * @param  callable(string): void|null  $logger
     * @return array<string, mixed>
     */
    public function sync(int $limit, bool $dryRun = false, bool $writeCsv = true, ?callable $logger = null): array
    {
        $settings = config('services.movie_sync', []);
        $apiKey = trim((string) config('services.tmdb.key'));
        $accessToken = trim((string) config('services.tmdb.access_token'));

        if ($apiKey === '' && $accessToken === '') {
            throw new RuntimeException('TMDB_API_KEY or TMDB_ACCESS_TOKEN must be configured.');
        }
        if ($limit < 1 || $limit > (int) ($settings['max_allowed_movies'] ?? 500)) {
            throw new RuntimeException('Movie limit is outside the allowed range.');
        }

        $report = [
            'requested' => $limit,
            'created' => 0,
            'backfilled' => 0,
            'existing' => 0,
            'without_stream' => 0,
            'adult_skipped' => 0,
            'failed' => 0,
            'pages_scanned' => 0,
            'dry_run' => $dryRun,
            'movies' => [],
        ];
        $seenThisRun = [];
        $maxPages = max(1, (int) ($settings['max_pages'] ?? 25));

        for ($page = 1; $page <= $maxPages && $report['created'] < $limit; $page++) {
            if ($logger) {
                $logger("Loading TMDB page {$page}...");
            }
            $items = $this->popularPage($page, $apiKey, $accessToken, $settings);
            $report['pages_scanned'] = $page;

            if ($items === []) {
                break;
            }

            $pageIds = collect($items)
                ->pluck('id')
                ->filter(fn ($id) => is_int($id) || ctype_digit((string) $id))
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values();
            $existingMedia = Media::withTrashed()
                ->where('type', 'movie')
                ->whereIn('tmdb_id', $pageIds)
                ->withCount(['streams as active_streams_count' => fn ($query) => $query->where('is_active', true)])
                ->get()
                ->keyBy(fn (Media $media) => (string) $media->tmdb_id);

            foreach ($items as $item) {
                if ($report['created'] >= $limit) {
                    break;
                }

                $tmdbId = isset($item['id']) ? (string) $item['id'] : '';
                if ($tmdbId === '' || isset($seenThisRun[$tmdbId])) {
                    continue;
                }
                $seenThisRun[$tmdbId] = true;

                if (($item['adult'] ?? false) === true && ! (bool) ($settings['allow_adult'] ?? false)) {
                    $report['adult_skipped']++;

                    continue;
                }

                try {
                    $existing = $existingMedia->get($tmdbId);
                    if ($existing && (int) $existing->active_streams_count > 0) {
                        $report['existing']++;

                        continue;
                    }

                    $streamUrl = $this->findStream($tmdbId, $settings, $logger);
                    if ($streamUrl === null && (bool) ($settings['require_stream'] ?? true)) {
                        $report['without_stream']++;

                        continue;
                    }

                    if ($existing) {
                        if (! $dryRun && $streamUrl !== null) {
                            $backfilled = DB::transaction(function () use ($existing, $streamUrl) {
                                $media = Media::withTrashed()->lockForUpdate()->find($existing->id);
                                if (! $media || $media->streams()->where('is_active', true)->exists()) {
                                    return false;
                                }

                                $media->streams()->create([
                                    'name' => 'Server 1',
                                    'url' => $streamUrl,
                                    'embed' => true,
                                    'quality' => null,
                                    'language' => null,
                                    'is_active' => true,
                                    'sort_order' => 0,
                                ]);

                                return true;
                            }, 3);

                            if (! $backfilled) {
                                $report['existing']++;

                                continue;
                            }
                        }

                        $report['backfilled']++;
                        if ($logger) {
                            $logger(($dryRun ? 'Would restore stream: ' : 'Restored stream: ').$existing->title.' (TMDB '.$tmdbId.')');
                        }

                        continue;
                    }

                    $payload = $this->mediaPayload($item);
                    if (! $dryRun) {
                        try {
                            $created = DB::transaction(function () use ($payload, $streamUrl) {
                                $existing = Media::withTrashed()
                                    ->where('type', 'movie')
                                    ->where('tmdb_id', $payload['tmdb_id'])
                                    ->lockForUpdate()
                                    ->first();
                                if ($existing) {
                                    return null;
                                }

                                $media = Media::create($payload);
                                if ($streamUrl !== null) {
                                    $media->streams()->create([
                                        'name' => 'Server 1',
                                        'url' => $streamUrl,
                                        'embed' => true,
                                        'quality' => null,
                                        'language' => null,
                                        'is_active' => true,
                                        'sort_order' => 0,
                                    ]);
                                }

                                return $media;
                            }, 3);
                        } catch (QueryException $error) {
                            if ($this->isUniqueViolation($error)) {
                                $report['existing']++;

                                continue;
                            }
                            throw $error;
                        }

                        if ($created === null) {
                            $report['existing']++;

                            continue;
                        }
                    }

                    $report['created']++;
                    $report['movies'][] = [
                        'title' => $payload['title'],
                        'type' => 'movie',
                        'release_date' => $payload['release_date'],
                        'tmdb_id' => $tmdbId,
                        'overview' => $payload['overview'],
                        'poster_url' => $payload['poster_path'],
                        'backdrop_url' => $payload['backdrop_path'],
                        'stream_url' => $streamUrl ?? '',
                        'language' => (string) ($item['original_language'] ?? ''),
                        'published' => '1',
                    ];
                    if ($logger) {
                        $logger(($dryRun ? 'Would add: ' : 'Added: ').$payload['title'].' (TMDB '.$tmdbId.')');
                    }
                } catch (\Throwable $error) {
                    report($error);
                    $report['failed']++;
                    if ($logger) {
                        $logger("Failed TMDB {$tmdbId}: ".$this->safeError($error));
                    }
                }
            }

            if (count($items) < 20) {
                break;
            }
        }

        if ($writeCsv && ! $dryRun && $report['movies'] !== []) {
            $report['csv_path'] = $this->writeCsv($report['movies'], (string) ($settings['csv_path'] ?? 'movie-sync/latest.csv'));
        }

        return $report;
    }

    /** @return array<int, array<string, mixed>> */
    private function popularPage(int $page, string $apiKey, string $accessToken, array $settings): array
    {
        $request = Http::acceptJson()
            ->timeout((int) ($settings['tmdb_timeout_seconds'] ?? 15))
            ->retry((int) ($settings['retries'] ?? 3), 1000, throw: false);
        if ($accessToken !== '') {
            $request = $request->withToken($accessToken);
        }

        try {
            $response = $request->get('https://api.themoviedb.org/3/movie/popular', [
                ...($accessToken === '' ? ['api_key' => $apiKey] : []),
                'language' => (string) ($settings['language'] ?? 'ar'),
                'page' => $page,
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Could not connect to TMDB after all retry attempts.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('TMDB returned HTTP '.$response->status().'.');
        }

        $items = $response->json('results');
        if (! is_array($items)) {
            throw new RuntimeException('TMDB returned an unexpected response.');
        }

        return array_values(array_filter($items, 'is_array'));
    }

    private function findStream(string $tmdbId, array $settings, ?callable $logger): ?string
    {
        $timeout = (int) ($settings['stream_timeout_seconds'] ?? 7);
        foreach ((array) ($settings['stream_sources'] ?? []) as $template) {
            $url = str_replace('{tmdb_id}', rawurlencode($tmdbId), (string) $template);

            try {
                $response = Http::timeout($timeout)
                    ->withOptions(['allow_redirects' => false])
                    ->head($url);
                $status = $response->status();
                if ($status >= 200 && $status < 400) {
                    if ($logger) {
                        $logger('Stream responded: '.$url);
                    }

                    return $url;
                }

                if ($status === 405) {
                    $fallback = Http::timeout($timeout)
                        ->withHeaders(['Range' => 'bytes=0-4095'])
                        ->withOptions(['allow_redirects' => false, 'stream' => true])
                        ->get($url);
                    if ($fallback->status() >= 200 && $fallback->status() < 400) {
                        if ($logger) {
                            $logger('Stream responded: '.$url);
                        }

                        return $url;
                    }
                }
            } catch (ConnectionException) {
                // Try the next source.
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function mediaPayload(array $item): array
    {
        $tmdbId = (string) $item['id'];
        $title = Str::limit(trim((string) ($item['title'] ?? $item['original_title'] ?? '')), 255, '');
        if ($title === '') {
            $title = 'Movie '.$tmdbId;
        }
        $slugTitle = Str::slug($title);
        $slugTitle = Str::substr($slugTitle !== '' ? $slugTitle : 'movie', 0, max(1, 254 - strlen($tmdbId)));

        return [
            'type' => 'movie',
            'title' => $title,
            'name' => $title,
            'slug' => $slugTitle.'-'.$tmdbId,
            'tmdb_id' => $tmdbId,
            'overview' => trim((string) ($item['overview'] ?? '')) ?: null,
            'poster_path' => ! empty($item['poster_path']) ? 'https://image.tmdb.org/t/p/w500'.$item['poster_path'] : null,
            'backdrop_path' => ! empty($item['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280'.$item['backdrop_path'] : null,
            'release_date' => $this->validDate($item['release_date'] ?? null),
            'vote_average' => max(0, min(10, (float) ($item['vote_average'] ?? 0))),
            'is_featured' => false,
            'is_recommended' => false,
            'is_pinned' => false,
            'is_premium' => false,
            'is_published' => true,
            'sort_order' => 0,
            'metadata' => [
                'original_language' => $item['original_language'] ?? null,
                'original_title' => $item['original_title'] ?? null,
                'popularity' => $item['popularity'] ?? null,
                'vote_count' => $item['vote_count'] ?? null,
                'automatic_import' => true,
            ],
        ];
    }

    private function validDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) {
            return null;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : null;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function writeCsv(array $rows, string $path): string
    {
        $handle = fopen('php://temp', 'w+b');
        if ($handle === false) {
            throw new RuntimeException('Could not create the CSV backup.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        $headers = ['title', 'type', 'release_date', 'tmdb_id', 'overview', 'poster_url', 'backdrop_url', 'stream_url', 'language', 'published'];
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($key) => $this->safeCsvCell($row[$key] ?? ''), $headers));
        }
        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        if ($contents === false || ! Storage::disk('local')->put($path, $contents)) {
            throw new RuntimeException('Could not write the CSV backup.');
        }

        return Storage::disk('local')->path($path);
    }

    private function safeError(\Throwable $error): string
    {
        return $error instanceof RuntimeException ? $error->getMessage() : $error::class;
    }

    private function safeCsvCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/u', $value) ? "'".$value : $value;
    }

    private function isUniqueViolation(QueryException $error): bool
    {
        return in_array((string) $error->getCode(), ['23000', '23505'], true)
            || str_contains(strtolower($error->getMessage()), 'media_type_tmdb_id_unique');
    }
}
