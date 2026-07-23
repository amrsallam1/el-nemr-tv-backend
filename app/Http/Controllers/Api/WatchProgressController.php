<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WatchProgressController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tmdb' => ['required', 'string', 'max:100'],
            'resumeWindow' => ['required', 'integer', 'min:0'],
            'resumePosition' => ['required', 'integer', 'min:0'],
            'movieDuration' => ['required', 'integer', 'min:0'],
            'deviceId' => ['nullable', 'string', 'max:255'],
        ]);

        $media = $this->findMedia($data['tmdb']);

        DB::table('watch_progress')->updateOrInsert(
            ['user_id' => $request->user()->id, 'media_id' => $media->id, 'episode_id' => null],
            [
                'position_seconds' => $data['resumePosition'],
                'duration_seconds' => $data['movieDuration'],
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return response()->json([
            'status' => 200,
            'message' => 'Progress saved.',
            'body' => $this->payload($request, $media, $data['resumeWindow'], $data['deviceId'] ?? null),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $media = $this->findMedia($id);
        $progress = DB::table('watch_progress')
            ->where('user_id', $request->user()->id)
            ->where('media_id', $media->id)
            ->whereNull('episode_id')
            ->first();

        if (! $progress) {
            return response()->json(null);
        }

        return response()->json($this->payload($request, $media, 0, null, $progress));
    }

    private function findMedia(string $id): Media
    {
        return Media::query()
            ->where('is_published', true)
            ->where(fn ($query) => $query->where('tmdb_id', $id)->orWhere('id', $id))
            ->firstOrFail();
    }

    private function payload(
        Request $request,
        Media $media,
        int $window,
        ?string $deviceId,
        ?object $progress = null,
    ): array {
        return [
            'user_resume_id' => $request->user()->id,
            'tmdb' => $media->tmdb_id ?? (string) $media->id,
            'deviceId' => $deviceId,
            'resumeWindow' => $window,
            'resumePosition' => $progress->position_seconds ?? (int) $request->input('resumePosition', 0),
            'movieDuration' => $progress->duration_seconds ?? (int) $request->input('movieDuration', 0),
            'type' => match ($media->type) {
                'series' => 'serie',
                'live' => 'streaming',
                default => $media->type,
            },
        ];
    }
}
