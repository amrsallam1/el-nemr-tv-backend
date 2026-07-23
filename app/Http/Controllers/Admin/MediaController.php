<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use App\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.media.index', [
            'items' => Media::query()
                ->when($request->filled('search'), fn ($query) => $query
                    ->where('title', 'like', '%'.$request->string('search').'%'))
                ->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.media.form', ['media' => new Media(), 'genres' => Genre::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $payload = Arr::only($data, [
            'type',
            'title',
            'slug',
            'overview',
            'poster_path',
            'backdrop_path',
            'release_date',
            'vote_average',
            'is_published',
            'is_featured',
            'is_premium',
        ]);

        $payload['slug'] = $payload['slug'] ?: Str::slug($payload['title']);

        $media = Media::create($payload);
        $media->genres()->sync($data['genre_ids'] ?? []);

        return redirect()->route('admin.media.index')->with('success', 'تمت إضافة المحتوى.');
    }

    public function edit(Media $media): View
    {
        return view('admin.media.form', [
            'media' => $media->load('genres'),
            'genres' => Genre::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $data = $this->validated($request, $media);
        $payload = Arr::only($data, [
            'type',
            'title',
            'slug',
            'overview',
            'poster_path',
            'backdrop_path',
            'release_date',
            'vote_average',
            'is_published',
            'is_featured',
            'is_premium',
        ]);

        $payload['slug'] = $payload['slug'] ?: Str::slug($payload['title']);

        $media->update($payload);
        $media->genres()->sync($data['genre_ids'] ?? []);

        return redirect()->route('admin.media.index')->with('success', 'تم تحديث المحتوى.');
    }

    public function destroy(Media $media): RedirectResponse
    {
        $media->delete();

        return back()->with('success', 'تم نقل المحتوى إلى المحذوفات.');
    }

    private function validated(Request $request, ?Media $media = null): array
    {
        $request->merge([
            'is_published' => $request->boolean('is_published'),
            'is_featured' => $request->boolean('is_featured'),
            'is_premium' => $request->boolean('is_premium'),
        ]);

        return $request->validate([
            'type' => ['required', Rule::in(['movie', 'series', 'anime', 'live'])],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('media')->ignore($media)],
            'overview' => ['nullable', 'string'],
            'poster_path' => ['nullable', 'string', 'max:2048'],
            'backdrop_path' => ['nullable', 'string', 'max:2048'],
            'release_date' => ['nullable', 'date'],
            'vote_average' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'is_published' => ['boolean'],
            'is_featured' => ['boolean'],
            'is_premium' => ['boolean'],
            'genre_ids' => ['nullable', 'array'],
            'genre_ids.*' => ['integer', 'exists:genres,id'],
        ]);
    }
}
