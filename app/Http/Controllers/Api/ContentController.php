<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ContentController extends Controller
{
    public function latest(): JsonResponse
    {
        return $this->collection('latest', $this->base()->latest()->limit(14));
    }

    public function featured(): JsonResponse
    {
        return $this->collection('featured', $this->base()->where('is_featured', true)->latest()->limit(20));
    }

    public function recommended(): JsonResponse
    {
        return $this->collection('recommended', $this->base()
            ->orderByDesc('is_recommended')->orderByDesc('vote_average')->limit(20));
    }

    public function trending(): JsonResponse
    {
        return $this->collection('trending', $this->base()->orderByDesc('views')->limit(20));
    }

    public function thisWeek(): JsonResponse
    {
        return $this->collection('thisweek', $this->base()
            ->where('created_at', '>=', now()->startOfWeek())->latest()->limit(20));
    }

    public function chosen(): JsonResponse
    {
        return $this->collection('choosed', $this->base()->inRandomOrder()->limit(20));
    }

    public function movies(): JsonResponse
    {
        return $this->collection('latest', $this->base()->where('type', 'movie')->latest()->limit(20));
    }

    public function series(): JsonResponse
    {
        return $this->collection('recents', $this->base()->where('type', 'series')->latest()->limit(20));
    }

    public function animes(): JsonResponse
    {
        return $this->collection('anime', $this->base()->where('type', 'anime')->latest()->limit(20));
    }

    public function live(): JsonResponse
    {
        return $this->collection('livetv', $this->base()->where('type', 'live')->latest()->limit(50));
    }

    public function show(Media $media): MediaResource
    {
        abort_unless($media->is_published, 404);

        return new MediaResource($media->load([
            'genres',
            'streams' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
            'seasons' => fn ($query) => $query->with([
                'episodes' => fn ($episodes) => $episodes->with([
                    'streams' => fn ($streams) => $streams->where('is_active', true)->orderBy('sort_order'),
                ])->orderBy('episode_number'),
            ])->orderBy('season_number'),
        ]));
    }

    private function base(): Builder
    {
        return Media::query()->where('is_published', true)->with('genres');
    }

    private function collection(string $key, Builder $query): JsonResponse
    {
        return response()->json([
            $key => MediaResource::collection($query->get())->resolve(),
        ]);
    }
}
