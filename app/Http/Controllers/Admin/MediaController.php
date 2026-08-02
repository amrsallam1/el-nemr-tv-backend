<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use App\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(Request $request): View
    {
        $sort = $request->string('sort')->toString();

        return view('admin.media.index', [
            'items' => Media::query()
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = '%'.$request->string('search').'%';
                    $query->where(function ($nested) use ($search) {
                        $nested->where('title', 'like', $search)
                            ->orWhere('name', 'like', $search)
                            ->orWhere('tmdb_id', 'like', $search);
                    });
                })
                ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
                ->when($request->filled('status'), fn ($query) => $query->where('is_published', $request->string('status') === 'published'))
                ->when($request->filled('featured'), fn ($query) => $query->where('is_featured', $request->string('featured') === 'yes'))
                ->when($request->filled('pinned'), fn ($query) => $query->where('is_pinned', $request->string('pinned') === 'yes'))
                ->when($request->filled('min_vote'), fn ($query) => $query->where('vote_average', '>=', $request->float('min_vote')))
                ->when($sort === 'views', fn ($query) => $query->orderByDesc('views'))
                ->when($sort === 'rating', fn ($query) => $query->orderByDesc('vote_average'))
                ->when($sort === 'order', fn ($query) => $query->orderByDesc('sort_order'))
                ->when(! in_array($sort, ['views', 'rating', 'order'], true), fn ($query) => $query->latest())
                ->paginate(20)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.media.form', ['media' => new Media, 'genres' => Genre::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $payload = Arr::only($data, [
            'type',
            'title',
            'name',
            'slug',
            'tmdb_id',
            'imdb_id',
            'overview',
            'poster_path',
            'backdrop_path',
            'release_date',
            'vote_average',
            'is_published',
            'is_featured',
            'is_recommended',
            'is_pinned',
            'is_premium',
            'sort_order',
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
            'name',
            'slug',
            'tmdb_id',
            'imdb_id',
            'overview',
            'poster_path',
            'backdrop_path',
            'release_date',
            'vote_average',
            'is_published',
            'is_featured',
            'is_recommended',
            'is_pinned',
            'is_premium',
            'sort_order',
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
            'is_recommended' => $request->boolean('is_recommended'),
            'is_pinned' => $request->boolean('is_pinned'),
            'is_premium' => $request->boolean('is_premium'),
        ]);

        return $request->validate([
            'type' => ['required', Rule::in(['movie', 'series', 'anime', 'live'])],
            'title' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('media')->ignore($media)],
            'tmdb_id' => ['nullable', 'string', 'max:64'],
            'imdb_id' => ['nullable', 'string', 'max:64'],
            'overview' => ['nullable', 'string'],
            'poster_path' => ['nullable', 'string', 'max:2048'],
            'backdrop_path' => ['nullable', 'string', 'max:2048'],
            'release_date' => ['nullable', 'date'],
            'vote_average' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'is_published' => ['boolean'],
            'is_featured' => ['boolean'],
            'is_recommended' => ['boolean'],
            'is_pinned' => ['boolean'],
            'is_premium' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'genre_ids' => ['nullable', 'array'],
            'genre_ids.*' => ['integer', 'exists:genres,id'],
        ]);
    }
}
