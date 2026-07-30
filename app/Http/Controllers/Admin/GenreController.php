<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GenreController extends Controller
{
    public function index(): View
    {
        return view('admin.genres.index', [
            'genres' => Genre::query()->withCount('media')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $slug = Str::slug($data['name']);
        $slug = $slug !== '' ? $slug : 'genre-'.Str::lower(Str::random(8));
        abort_if(Genre::where('slug', $slug)->exists(), 422, 'التصنيف موجود بالفعل.');
        Genre::create(['name' => $data['name'], 'slug' => $slug]);

        return back()->with('success', 'تمت إضافة التصنيف.');
    }

    public function update(Request $request, Genre $genre): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', Rule::unique('genres')->ignore($genre)],
        ]);
        $genre->update($data);

        return back()->with('success', 'تم تحديث التصنيف.');
    }

    public function destroy(Genre $genre): RedirectResponse
    {
        $genre->delete();

        return back()->with('success', 'تم حذف التصنيف.');
    }
}
