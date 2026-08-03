<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stream;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PlaybackController extends Controller
{
    public function __invoke(Stream $stream): RedirectResponse
    {
        abort_unless($stream->is_active && $stream->source_url, 404);

        $mediaUrl = Cache::remember(
            'worker-playback:v1:'.$stream->id,
            now()->addSeconds((int) config('services.content_worker.playback_cache_seconds', 120)),
            fn () => $this->resolve((string) $stream->source_url),
        );

        return redirect()->away($mediaUrl, 302, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    private function resolve(string $sourceUrl): string
    {
        $source = parse_url($sourceUrl);
        $allowedHosts = array_map('strtolower', (array) config('services.content_worker.allowed_source_hosts', []));
        if (! is_array($source)
            || ($source['scheme'] ?? null) !== 'https'
            || ! in_array(strtolower((string) ($source['host'] ?? '')), $allowedHosts, true)) {
            abort(403, 'The stored source URL is not approved.');
        }

        $workerUrl = rtrim((string) config('services.content_worker.url'), '/').'/';
        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.content_worker.timeout_seconds', 20))
                ->retry((int) config('services.content_worker.retries', 2), 500, throw: false)
                ->get($workerUrl, ['action' => 'series', 'series' => $sourceUrl]);
        } catch (ConnectionException) {
            abort(502, 'Could not connect to the playback resolver.');
        }

        if (! $response->successful()) {
            abort(502, 'Playback resolver returned HTTP '.$response->status().'.');
        }

        $mediaUrl = trim((string) $response->json('media_src'));
        $parts = parse_url($mediaUrl);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || empty($parts['host'])) {
            abort(404, 'No playable stream is available.');
        }

        return $mediaUrl;
    }
}
