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
    /** @return array<string, int|bool|string> */
    public function sync(string $type, int $pages, int $limit, bool $dryRun = false, ?callable $logger = null, array $years = []): array
    {
        if (! in_array($type, ['movies', 'series'], true)) {
            throw new RuntimeException('Type must be movies or series.');
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
            'updated' => 0, 'skipped' => 0, 'failed' => 0,
        ];
        $seen = [];

        for ($page = 1; $page <= $pages && $report['scanned'] < $limit; $page++) {
            $items = $this->catalogPage($type, $page, $settings);
            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                if ($report['scanned'] >= $limit) {
                    break 2;
                }

                $identity = $this->identity($item, $type);
                if ($identity === null || isset($seen[$identity['key']])) {
                    $report['skipped']++;
                    continue;
                }
                $seen[$identity['key']] = true;
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
                            if ($logger) {
                                $logger('Skipped outside requested years: '.$identity['title']);
                            }
                            continue;
                        }
                        $this->persist($identity, $item, $details, $year);
                    }

                    $exists ? $report['updated']++ : $report['created']++;
                    if ($logger) {
                        $logger(($dryRun ? 'Would sync: ' : 'Synced: ').$identity['title']);
                    }
                } catch (\Throwable $error) {
                    report($error);
                    $report['failed']++;
                    if ($logger) {
                        $logger('Failed '.$identity['title'].': '.($error instanceof RuntimeException ? $error->getMessage() : $error::class));
                    }
                }
            }
        }

        return $report;
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
