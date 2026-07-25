<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TmdbService
{
    public function find(string $type, string $title, ?string $tmdbId = null): array
    {
        $key = config('services.tmdb.key');
        if (!$key) return [];

        $kind = $type === 'movie' ? 'movie' : 'tv';
        $url = $tmdbId
            ? "https://api.themoviedb.org/3/{$kind}/".$tmdbId
            : "https://api.themoviedb.org/3/search/{$kind}";
        $params = ['api_key' => $key, 'language' => 'ar-SA'];
        if (!$tmdbId) $params['query'] = $title;
        $response = Http::timeout(10)->acceptJson()->get($url, $params);
        if (!$response->successful()) return [];
        $data = $response->json();
        $item = $tmdbId ? $data : ($data['results'][0] ?? null);
        if (!$item) return [];

        return [
            'tmdb_id' => (string) ($item['id'] ?? $tmdbId),
            'overview' => $item['overview'] ?? null,
            'poster_path' => !empty($item['poster_path']) ? 'https://image.tmdb.org/t/p/w500'.$item['poster_path'] : null,
            'backdrop_path' => !empty($item['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280'.$item['backdrop_path'] : null,
            'release_date' => $item['release_date'] ?? $item['first_air_date'] ?? null,
            'vote_average' => $item['vote_average'] ?? 0,
        ];
    }
}
