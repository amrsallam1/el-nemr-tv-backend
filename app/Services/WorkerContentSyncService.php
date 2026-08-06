<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class WorkerContentSyncService
{
    /**
     * Imports matching Worker results on demand so legacy Android search can
     * keep using normal Media IDs, details, streams and download records.
     *
     * @return array{created:int,updated:int,failed:int}
     */
    public function syncSearchResults(string $query, int $limit = 30): array
    {
        $query = trim($query);
        if ($query === '' || mb_strlen($query) > 120) {
            return ['created' => 0, 'updated' => 0, 'failed' => 0];
        }

        $settings = config('services.content_worker', []);
        $payload = $this->workerRequest(['action' => 'search', 'q' => $query], $settings);
        $items = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $report = ['created' => 0, 'updated' => 0, 'failed' => 0];

        foreach (array_slice($items, 0, max(1, min($limit, 50))) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $path = (string) (parse_url((string) ($item['href'] ?? ''), PHP_URL_PATH) ?? '');
            $type = str_starts_with($path, '/series/') ? 'series' : (str_starts_with($path, '/movie/') ? 'movies' : null);
            if ($type === null || ($identity = $this->identity($item, $type)) === null) {
                continue;
            }
            try {
                $exists = Media::withTrashed()
                    ->where('source', 'content-worker')
                    ->where('type', $identity['media_type'])
                    ->where('source_id', $identity['source_id'])
                    ->exists();
                $details = $this->details($identity['url'], $settings);
                $this->persist($identity, $item, $details, $this->detectYear($item, $details));
                $report[$exists ? 'updated' : 'created']++;
            } catch (\Throwable $error) {
                report($error);
                $report['failed']++;
            }
        }

        return $report;
    }

    /** @return array<string, int|bool|string> */
    public function sync(string $type, int $pages, int $limit, bool $dryRun = false, ?callable $logger = null, array $years = [], string $language = 'all', bool $targetNew = false): array
    {
        if (! in_array($type, ['movies', 'series'], true)) {
            throw new RuntimeException('Type must be movies or series.');
        }
        if (! in_array($language, ['all', 'arabic', 'english'], true)) {
            throw new RuntimeException('Language must be all, arabic or english.');
        }

        $settings = config('services.content_worker', []);
        $years = $years !== [] ? $years : array_values(array_unique($settings['allowed_years'] ?? []));
        $maxPages = (int) ($settings['max_pages'] ?? 10);
        $maxItems = (int) ($settings['max_items'] ?? 200);
        if ($pages < 1 || $pages > $maxPages || $limit < 1 || $limit > $maxItems) {
            throw new RuntimeException('Requested pages or item limit is outside the configured range.');
        }

        $report = [
            'type' => $type, 'dry_run' => $dryRun, 'scanned' => 0, 'created' => 0,
            'updated' => 0, 'skipped' => 0, 'failed' => 0, 'items' => [],
        ];
        $seen = [];

        for ($page = 1; $page <= $pages && $this->shouldContinue($report, $limit, $maxItems, $targetNew); $page++) {
            $items = $this->catalogPage($type, $page, $settings);
            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                if (! $this->shouldContinue($report, $limit, $maxItems, $targetNew)) {
                    break 2;
                }

                $identity = $this->identity($item, $type);
                if ($identity === null || isset($seen[$identity['key']])) {
                    $report['skipped']++;
                    continue;
                }
                $seen[$identity['key']] = true;
                if (! $this->matchesLanguage($identity['title'], $language)) {
                    $report['skipped']++;
                    $report['items'][] = $this->itemReport($identity, 'skipped', 'language_filter');
                    continue;
                }
                $report['scanned']++;

                try {
                    $exists = Media::withTrashed()
                        ->where('source', 'content-worker')
                        ->where('type', $identity['media_type'])
                        ->where('source_id', $identity['source_id'])
                        ->exists();

                    if (! $dryRun) {
                        $details = $this->details($identity['url'], $settings);
                        $year = $this->detectYear($item, $details);
                        if ($year === null && $type === 'series' && isset($details['episodes'][0]['link'])) {
                            $year = $this->detectYear([], $this->details((string) $details['episodes'][0]['link'], $settings));
                        }
                        if ($years !== [] && ($year === null || ! in_array($year, $years, true))) {
                            $report['skipped']++;
                            $report['items'][] = $this->itemReport($identity, 'skipped', $year === null ? 'year_unknown' : 'year_filter', $year);
                            if ($logger) {
                                $logger('Skipped outside requested years: '.$identity['title']);
                            }
                            continue;
                        }
                        $this->persist($identity, $item, $details, $year);
                    }

                    $exists ? $report['updated']++ : $report['created']++;
                    $report['items'][] = $this->itemReport($identity, $dryRun ? 'preview' : ($exists ? 'updated' : 'created'), null, $year ?? null);
                    if ($logger) {
                        $logger(($dryRun ? 'Would sync: ' : 'Synced: ').$identity['title']);
                    }
                } catch (\Throwable $error) {
                    report($error);
                    $report['failed']++;
                    $report['items'][] = $this->itemReport($identity, 'failed', $error instanceof RuntimeException ? $error->getMessage() : $error::class);
                    if ($logger) {
                        $logger('Failed '.$identity['title'].': '.($error instanceof RuntimeException ? $error->getMessage() : $error::class));
                    }
                }
            }
        }

        return $report;
    }

    private function shouldContinue(array $report, int $limit, int $maxItems, bool $targetNew): bool
    {
        return $report['scanned'] < $maxItems
            && ($targetNew ? $report['created'] < $limit : $report['scanned'] < $limit);
    }

    private function matchesLanguage(string $title, string $language): bool
    {
        if ($language === 'all') {
            return true;
        }
        $arabic = preg_match('/[\x{0600}-\x{06FF}]/u', $title) === 1;
        return $language === 'arabic' ? $arabic : ! $arabic;
    }

    private function itemReport(array $identity, string $status, ?string $reason = null, ?int $year = null): array
    {
        return array_filter([
            'title' => $identity['title'],
            'type' => $identity['media_type'],
            'source_id' => $identity['source_id'],
            'status' => $status,
            'reason' => $reason,
            'year' => $year,
        ], fn ($value) => $value !== null);
    }

    /** @return array<int, array<string, mixed>> */
    private function catalogPage(string $type, int $page, array $settings): array
    {
        $origin = rtrim((string) ($settings['catalog_origin'] ?? 'https://akwam.it'), '/');
        $genreUrl = $origin.'/'.($type === 'movies' ? 'movies' : 'series');
        $payload = $this->workerRequest([
            'action' => 'genre', 'genre' => $genreUrl, 'p' => $page,
        ], $settings);
        $items = $payload['data'] ?? null;

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /** @return array<string, mixed> */
    private function details(string $url, array $settings): array
    {
        return $this->workerRequest(['action' => 'series', 'series' => $url], $settings);
    }

    /** @return array<string, mixed> */
    private function workerRequest(array $query, array $settings): array
    {
        $workerUrl = rtrim((string) ($settings['url'] ?? ''), '/').'/';
        if (! filter_var($workerUrl, FILTER_VALIDATE_URL) || ! str_starts_with($workerUrl, 'https://')) {
            throw new RuntimeException('CONTENT_WORKER_URL must be a valid HTTPS URL.');
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) ($settings['timeout_seconds'] ?? 20))
                ->retry((int) ($settings['retries'] ?? 2), 500, throw: false)
                ->get($workerUrl, $query);
        } catch (ConnectionException) {
            throw new RuntimeException('Could not connect to the content worker.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Content worker returned HTTP '.$response->status().'.');
        }
        $payload = $response->json();
        if (! is_array($payload) || ($payload['status'] ?? null) === 'error') {
            throw new RuntimeException((string) ($payload['message'] ?? 'Content worker returned invalid JSON.'));
        }

        return $payload;
    }

    /** @return array{key:string,source_id:string,media_type:string,url:string,title:string}|null */
    private function identity(array $item, string $type): ?array
    {
        $url = trim((string) ($item['href'] ?? ''));
        $title = trim((string) ($item['title'] ?? ''));
        $expectedPath = $type === 'movies' ? 'movie' : 'series';
        if ($title === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || ! preg_match('~^/'.$expectedPath.'/(\d+)(?:/|$)~', (string) ($parts['path'] ?? ''), $match)) {
            return null;
        }

        $mediaType = $type === 'movies' ? 'movie' : 'series';
        return [
            'key' => $mediaType.':'.$match[1], 'source_id' => $match[1],
            'media_type' => $mediaType, 'url' => $url, 'title' => Str::limit($title, 255, ''),
        ];
    }

    private function persist(array $identity, array $item, array $details, ?int $year): void
    {
        DB::transaction(function () use ($identity, $item, $details, $year): void {
            $media = Media::withTrashed()->updateOrCreate(
                [
                    'source' => 'content-worker', 'type' => $identity['media_type'],
                    'source_id' => $identity['source_id'],
                ],
                [
                    'title' => $identity['title'],
                    'name' => $identity['media_type'] === 'series' ? $identity['title'] : null,
                    'slug' => $this->slug($identity),
                    'source_url' => $identity['url'],
                    'poster_path' => filter_var($item['img'] ?? null, FILTER_VALIDATE_URL) ?: null,
                    'backdrop_path' => filter_var($item['img'] ?? null, FILTER_VALIDATE_URL) ?: null,
                    'release_date' => $year ? $year.'-01-01' : null,
                    'is_published' => true,
                    'metadata' => [
                        'automatic_import' => true,
                        'worker_title' => $details['movie_title'] ?? null,
                        'last_synced_at' => now()->toIso8601String(),
                    ],
                ]
            );
            if ($media->trashed()) {
                $media->restore();
            }

            $episodes = is_array($details['episodes'] ?? null) ? $details['episodes'] : [];
            if ($identity['media_type'] === 'series' && $episodes !== []) {
                $season = $media->seasons()->firstOrCreate(
                    ['season_number' => 1], ['name' => 'الموسم 1']
                );
                foreach ($episodes as $episodeData) {
                    $number = filter_var($episodeData['num'] ?? null, FILTER_VALIDATE_INT);
                    $sourceUrl = filter_var($episodeData['link'] ?? null, FILTER_VALIDATE_URL);
                    if (! is_int($number) || $number < 1 || ! $sourceUrl) {
                        continue;
                    }
                    $episode = $season->episodes()->updateOrCreate(
                        ['episode_number' => $number], ['name' => 'الحلقة '.$number]
                    );
                    $this->upsertWorkerStream($media, $episode->id, $sourceUrl);
                }
            } else {
                $this->upsertWorkerStream($media, null, $identity['url']);
            }
        }, 3);
    }

    private function detectYear(array $item, array $details): ?int
    {
        $values = [
            $item['title'] ?? null,
            $item['href'] ?? null,
            $details['movie_title'] ?? null,
            $details['media_src'] ?? null,
            $details['download_src'] ?? null,
        ];
        foreach ($values as $value) {
            if (preg_match('/(?:^|\D)(20\d{2})(?:\D|$)/', (string) $value, $match)) {
                return (int) $match[1];
            }
        }

        return null;
    }

    private function upsertWorkerStream(Media $media, ?int $episodeId, string $sourceUrl): void
    {
        $media->streams()->updateOrCreate(
            ['episode_id' => $episodeId, 'name' => 'El-Nemr Worker'],
            [
                'url' => $sourceUrl, 'source_url' => $sourceUrl, 'embed' => false,
                'quality' => 'Auto', 'language' => 'ar', 'is_active' => true, 'sort_order' => 0,
            ]
        );
    }

    private function slug(array $identity): string
    {
        $title = Str::slug($identity['title']);
        return Str::limit(($title !== '' ? $title : $identity['media_type']).'-worker-'.$identity['source_id'], 255, '');
    }
}
