<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Season;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EpisodeController extends Controller
{
    public function index(Season $season): JsonResponse
    {
        return response()->json(['data' => $season->episodes()->with('streams')->orderBy('episode_number')->get()]);
    }

    public function store(Request $request, Season $season): JsonResponse
    {
        return response()->json($season->episodes()->create($this->validated($request, $season)), 201);
    }

    public function update(Request $request, Season $season, Episode $episode): JsonResponse
    {
        $this->ownedBy($season, $episode);
        $episode->update($this->validated($request, $season, $episode));

        return response()->json($episode);
    }

    public function destroy(Season $season, Episode $episode): JsonResponse
    {
        $this->ownedBy($season, $episode);
        $episode->delete();

        return response()->json(['message' => 'Episode deleted.']);
    }

    private function validated(Request $request, Season $season, ?Episode $episode = null): array
    {
        return $request->validate([
            'episode_number' => [
                $episode ? 'sometimes' : 'required',
                'integer', 'min:0', 'max:9999',
                Rule::unique('episodes')->where('season_id', $season->id)->ignore($episode),
            ],
            'name' => [$episode ? 'sometimes' : 'required', 'string', 'max:255'],
            'overview' => ['nullable', 'string'],
            'still_path' => ['nullable', 'string', 'max:2048'],
            'air_date' => ['nullable', 'date'],
            'runtime' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_premium' => ['sometimes', 'boolean'],
        ]);
    }

    private function ownedBy(Season $season, Episode $episode): void
    {
        abort_unless($episode->season_id === $season->id, 404);
    }
}
