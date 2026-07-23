<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Season;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeasonController extends Controller
{
    public function index(Media $media): JsonResponse
    {
        return response()->json(['data' => $media->seasons()->with('episodes')->orderBy('season_number')->get()]);
    }

    public function store(Request $request, Media $media): JsonResponse
    {
        abort_unless(in_array($media->type, ['series', 'anime'], true), 422, 'Seasons are only available for series and anime.');
        $season = $media->seasons()->create($this->validated($request, $media));

        return response()->json($season, 201);
    }

    public function update(Request $request, Media $media, Season $season): JsonResponse
    {
        $this->ownedBy($media, $season);
        $season->update($this->validated($request, $media, $season));

        return response()->json($season);
    }

    public function destroy(Media $media, Season $season): JsonResponse
    {
        $this->ownedBy($media, $season);
        $season->delete();

        return response()->json(['message' => 'Season deleted.']);
    }

    private function validated(Request $request, Media $media, ?Season $season = null): array
    {
        return $request->validate([
            'season_number' => [
                $season ? 'sometimes' : 'required',
                'integer', 'min:0', 'max:999',
                Rule::unique('seasons')->where('media_id', $media->id)->ignore($season),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'overview' => ['nullable', 'string'],
            'poster_path' => ['nullable', 'string', 'max:2048'],
            'air_date' => ['nullable', 'date'],
        ]);
    }

    private function ownedBy(Media $media, Season $season): void
    {
        abort_unless($season->media_id === $media->id, 404);
    }
}
