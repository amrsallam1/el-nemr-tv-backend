<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Media;
use App\Models\Stream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StreamController extends Controller
{
    public function storeForMedia(Request $request, Media $media): JsonResponse
    {
        $stream = $media->streams()->create($this->validated($request));

        return response()->json($stream, 201);
    }

    public function storeForEpisode(Request $request, Episode $episode): JsonResponse
    {
        $stream = $episode->streams()->create($this->validated($request));

        return response()->json($stream, 201);
    }

    public function update(Request $request, Stream $stream): JsonResponse
    {
        $stream->update($this->validated($request, true));

        return response()->json($stream);
    }

    public function destroy(Stream $stream): JsonResponse
    {
        $stream->delete();

        return response()->json(['message' => 'Stream deleted.']);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'url' => [$updating ? 'sometimes' : 'required', 'url:http,https', 'max:8192'],
            'embed' => ['sometimes', 'boolean'],
            'quality' => ['nullable', 'string', 'max:50'],
            'language' => ['nullable', 'string', 'max:50'],
            'headers' => ['nullable', 'array'],
            'headers.*' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }
}
