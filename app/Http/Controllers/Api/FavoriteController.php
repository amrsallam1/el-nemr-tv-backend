<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function store(Request $request, string $type, Media $media): JsonResponse
    {
        $this->assertType($type, $media);
        $request->user()->favorites()->syncWithoutDetaching([$media->id]);

        return response()->json(['status' => 1, 'message' => 'Added to favorites.']);
    }

    public function show(Request $request, string $type, Media $media): JsonResponse
    {
        $this->assertType($type, $media);
        $exists = $request->user()->favorites()->whereKey($media->id)->exists();

        return response()->json([
            'status' => $exists ? 1 : 0,
            'message' => $exists ? 'Favorite.' : 'Not favorite.',
        ]);
    }

    public function destroy(Request $request, string $type, Media $media): JsonResponse
    {
        $this->assertType($type, $media);
        $request->user()->favorites()->detach($media->id);

        return response()->json(['status' => 0, 'message' => 'Removed from favorites.']);
    }

    private function assertType(string $type, Media $media): void
    {
        $expected = match ($type) {
            'movie' => 'movie',
            'serie' => 'series',
            'anime' => 'anime',
            'streaming' => 'live',
            default => null,
        };

        abort_unless($expected && $media->type === $expected && $media->is_published, 404);
    }
}
