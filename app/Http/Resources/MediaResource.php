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
            'series' => 'serie',
            'live' => 'streaming',
            default => $this->type,
        };

        return [
            'id' => (string) $this->id,
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
            'premuim' => $this->is_premium ? 1 : 0,
            'genres' => $this->whenLoaded('genres', fn () => $this->genres->map(fn ($genre) => [
                'id' => $genre->id,
                'name' => $genre->name,
            ])->values()),
            'seasons' => $this->whenLoaded('seasons'),
            'videos' => $this->whenLoaded('streams', fn () => $this->streams->map(fn ($stream) => [
                'id' => $stream->id,
                'name' => $stream->name,
                'server' => $stream->name,
                'link' => $stream->url,
                'lang' => $stream->language,
                'type' => $stream->quality,
                'hls' => str_contains(strtolower($stream->url), '.m3u8') ? 1 : 0,
                'header' => $stream->headers ? json_encode($stream->headers) : null,
                'useragent' => null,
                // Treat explicit embed streams, and common embed/player page URLs,
                // as web pages instead of sending them to ExoPlayer as media files.
                'embed' => ($stream->embed || preg_match('/(?:\/embed\/|\/iframe\/|[?&]embed=1)/i', $stream->url)) ? 1 : 0,
                'youtubelink' => 0,
                'supported_hosts' => 0,
                'external' => 0,
                'linkpremuim' => 0,
                'downloadonly' => 0,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
