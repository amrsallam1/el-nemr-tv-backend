<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $type = match ($this->type) {
            'movie' => 'Movie',
            'series' => 'Serie',
            'anime' => 'Anime',
            'live' => 'Streaming',
            default => ucfirst((string) $this->type),
        };

        return [
            'id' => (string) $this->id,
            // Required by the legacy Android Featured model/adapters.
            'featured_id' => $this->id,
            'tmdb_id' => $this->tmdb_id,
            'imdb_external_id' => $this->imdb_id,
            'title' => $this->title,
            // The legacy Android adapter uses a non-null "name" to identify
            // series and opens SerieDetailsActivity. Movies must only use title.
            'name' => in_array($this->type, ['series', 'anime', 'live'], true)
                ? ($this->name ?? $this->title)
                : null,
            'type' => $type,
            'is_anime' => $this->type === 'anime' ? 1 : 0,
            'overview' => $this->overview,
            'poster_path' => $this->poster_path,
            'backdrop_path' => $this->backdrop_path,
            'preview_path' => $this->preview_path,
            'vote_average' => (float) $this->vote_average,
            'views' => (string) $this->views,
            'runtime' => $this->runtime ? (string) $this->runtime : null,
            'release_date' => $this->release_date?->format('Y-m-d'),
            'first_air_date' => $this->type === 'series'
                ? $this->release_date?->format('Y-m-d')
                : null,
            'premuim' => $this->is_premium ? 1 : 0,
            'genres' => $this->whenLoaded('genres', fn () => $this->genres->map(fn ($genre) => [
                'id' => $genre->id,
                'name' => $genre->name,
            ])->values()->all()),
            'seasons' => $this->whenLoaded('seasons'),
            'videos' => $this->whenLoaded('streams', fn () => $this->streams->map(function ($stream) {
                $link = $stream->source_url
                    ? route('playback.resolve', ['stream' => $stream->id])
                    : (string) $stream->url;
                // vsem.ru is frequently blocked by mobile networks. Use the
                // configured public embed fallback for those legacy records.
                if (preg_match('/vsem(?:bed|b)?\.ru/i', $link) && $this->tmdb_id) {
                    $link = 'https://vidsrc.to/embed/movie/'.rawurlencode((string) $this->tmdb_id);
                }
                return [
                'id' => $stream->id,
                'name' => $stream->name,
                'server' => $stream->name,
                'link' => $link,
                'lang' => $stream->language,
                'type' => $stream->quality,
                'hls' => str_contains(strtolower($link), '.m3u8') ? 1 : 0,
                'header' => $stream->headers ? json_encode($stream->headers) : null,
                'useragent' => null,
                // Treat explicit embed streams, and common embed/player page URLs,
                // as web pages instead of sending them to ExoPlayer as media files.
                'embed' => ($stream->embed || preg_match('/(?:\/embed(?:[\/_-]|$)|\/iframe\/|[?&]embed=1)/i', $link)) ? 1 : 0,
                'youtubelink' => 0,
                'supported_hosts' => 0,
                'external' => 0,
                'linkpremuim' => 0,
                'downloadonly' => 0,
                ];
            })->values()),
            'downloads' => $this->whenLoaded('streams', fn () => $this->streams
                ->whereNotNull('source_url')
                ->map(fn ($stream) => [
                    'id' => $stream->id,
                    'name' => 'تحميل داخل التطبيق',
                    'server' => 'El-Nemr TV',
                    'link' => route('playback.resolve', ['stream' => $stream->id]),
                    'lang' => $stream->language,
                    'type' => $stream->quality,
                    'hls' => 0,
                    'header' => null,
                    'useragent' => null,
                    'embed' => 0,
                    'youtubelink' => 0,
                    'supported_hosts' => 0,
                    'external' => 0,
                    'linkpremuim' => 0,
                    'downloadonly' => 1,
                ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
