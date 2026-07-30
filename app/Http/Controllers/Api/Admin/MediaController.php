<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $media = Media::query()
            ->with('genres')
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search').'%';
                $query->where(fn ($nested) => $nested
                    ->where('title', 'like', $search)
                    ->orWhere('name', 'like', $search));
            })
            ->latest()
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return response()->json([
            'data' => MediaResource::collection($media->items())->resolve(),
            'meta' => [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $payload = Arr::only($data, [
            'type',
            'title',
            'slug',
            'name',
            'tmdb_id',
            'imdb_id',
            'overview',
            'poster_path',
            'backdrop_path',
            'preview_path',
            'release_date',
            'runtime',
            'vote_average',
            'is_featured',
            'is_recommended',
            'is_pinned',
            'is_premium',
            'is_published',
            'sort_order',
            'metadata',
        ]);

        $payload['slug'] = trim((string) ($payload['slug'] ?? '')) ?: Str::slug($payload['title']);

        $media = Media::create($payload);
        $media->genres()->sync($request->input('genre_ids', []));

        return (new MediaResource($media->load('genres')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Media $media): MediaResource
    {
        return new MediaResource($media->load(['genres', 'seasons.episodes.streams', 'streams']));
    }

    public function update(Request $request, Media $media): MediaResource
    {
        $data = $this->validated($request, $media);
        $payload = Arr::only($data, [
            'type',
            'title',
            'slug',
            'name',
            'tmdb_id',
            'imdb_id',
            'overview',
            'poster_path',
            'backdrop_path',
            'preview_path',
            'release_date',
            'runtime',
            'vote_average',
            'is_featured',
            'is_recommended',
            'is_pinned',
            'is_premium',
            'is_published',
            'sort_order',
            'metadata',
        ]);

        if (array_key_exists('slug', $payload)) {
            $payload['slug'] = trim((string) $payload['slug']) ?: Str::slug((string) ($payload['title'] ?? $media->title));
        }

        $media->update($payload);

        if ($request->has('genre_ids')) {
            $media->genres()->sync($request->input('genre_ids', []));
        }

        return new MediaResource($media->fresh()->load('genres'));
    }

    public function destroy(Media $media): JsonResponse
    {
        $media->delete();

        return response()->json(['message' => 'Content moved to trash.']);
    }

    private function validated(Request $request, ?Media $media = null): array
    {
        return $request->validate([
            'type' => [$media ? 'sometimes' : 'required', Rule::in(['movie', 'series', 'anime', 'live'])],
            'title' => [$media ? 'sometimes' : 'required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'slug' => [$media ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:255', Rule::unique('media')->ignore($media)],
            'tmdb_id' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('media', 'tmdb_id')
                    ->where(fn ($query) => $query->where('type', $request->input('type', $media?->type)))
                    ->ignore($media),
            ],
            'imdb_id' => ['nullable', 'string', 'max:100'],
            'overview' => ['nullable', 'string'],
            'poster_path' => ['nullable', 'string', 'max:2048'],
            'backdrop_path' => ['nullable', 'string', 'max:2048'],
            'preview_path' => ['nullable', 'string', 'max:2048'],
            'release_date' => ['nullable', 'date'],
            'runtime' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'vote_average' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_recommended' => ['sometimes', 'boolean'],
            'is_pinned' => ['sometimes', 'boolean'],
            'is_premium' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'genre_ids' => ['sometimes', 'array'],
            'genre_ids.*' => ['integer', 'exists:genres,id'],
        ]);
    }
}
