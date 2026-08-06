<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\WorkerContentSyncService;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __invoke(string $query, WorkerContentSyncService $worker): JsonResponse
    {
        $term = trim(urldecode($query));

        if ($term === '' || mb_strlen($term) > 120) {
            return response()->json(['search' => []]);
        }

        // Search the upstream catalog as well as the local library. Results
        // are persisted first so Android receives real Media IDs and can open
        // details, streams, episodes and downloads normally.
        $worker->syncSearchResults($term);

        $like = '%'.mb_strtolower($term).'%';
        $items = Media::query()
            ->where('is_published', true)
            ->with('genres')
            ->where(function ($builder) use ($term, $like) {
                $builder
                    ->whereRaw("LOWER(COALESCE(title, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(name, '')) LIKE ?", [$like])
                    ->orWhere('tmdb_id', $term);

                if (ctype_digit($term)) {
                    $builder->orWhereKey((int) $term);
                }
            })
            ->orderByDesc('views')
            ->latest('updated_at')
            ->limit(50)
            ->get();

        return response()->json([
            'search' => MediaResource::collection($items)->resolve(),
        ])->header('Cache-Control', 'public, max-age=60');
    }
}
