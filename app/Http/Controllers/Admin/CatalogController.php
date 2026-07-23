<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Media;
use App\Models\Season;
use App\Models\Stream;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function show(Media $media): View
    {
        return view('admin.media.catalog', [
            'media' => $media->load([
                'streams' => fn ($query) => $query->orderBy('sort_order'),
                'seasons' => fn ($query) => $query->with([
                    'episodes' => fn ($episodes) => $episodes
                        ->with(['streams' => fn ($streams) => $streams->orderBy('sort_order')])
                        ->orderBy('episode_number'),
                ])->orderBy('season_number'),
            ]),
        ]);
    }

    public function storeSeason(Request $request, Media $media): RedirectResponse
    {
        abort_unless(in_array($media->type, ['series', 'anime'], true), 422);
        $media->seasons()->create($request->validate([
            'season_number' => ['required', 'integer', 'min:0', Rule::unique('seasons')->where('media_id', $media->id)],
            'name' => ['nullable', 'string', 'max:255'],
        ]));

        return back()->with('success', 'تمت إضافة الموسم.');
    }

    public function destroySeason(Media $media, Season $season): RedirectResponse
    {
        abort_unless($season->media_id === $media->id, 404);
        $season->delete();

        return back()->with('success', 'تم حذف الموسم وحلقاته.');
    }

    public function storeEpisode(Request $request, Season $season): RedirectResponse
    {
        $season->episodes()->create($request->validate([
            'episode_number' => ['required', 'integer', 'min:0', Rule::unique('episodes')->where('season_id', $season->id)],
            'name' => ['required', 'string', 'max:255'],
            'still_path' => ['nullable', 'string', 'max:2048'],
        ]));

        return back()->with('success', 'تمت إضافة الحلقة.');
    }

    public function destroyEpisode(Episode $episode): RedirectResponse
    {
        $episode->delete();

        return back()->with('success', 'تم حذف الحلقة.');
    }

    public function storeMediaStream(Request $request, Media $media): RedirectResponse
    {
        $media->streams()->create($this->streamData($request));

        return back()->with('success', 'تمت إضافة سيرفر التشغيل.');
    }

    public function storeEpisodeStream(Request $request, Episode $episode): RedirectResponse
    {
        $episode->streams()->create($this->streamData($request));

        return back()->with('success', 'تمت إضافة سيرفر الحلقة.');
    }

    public function destroyStream(Stream $stream): RedirectResponse
    {
        $stream->delete();

        return back()->with('success', 'تم حذف سيرفر التشغيل.');
    }

    private function streamData(Request $request): array
    {
        return $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'url' => ['required', 'url:http,https', 'max:8192'],
            'quality' => ['nullable', 'string', 'max:50'],
            'language' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
