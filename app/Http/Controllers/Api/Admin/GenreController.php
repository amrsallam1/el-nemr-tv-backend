<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GenreController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Genre::query()->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $genre = Genre::create($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'unique:genres,slug'],
        ]));

        return response()->json($genre, 201);
    }

    public function update(Request $request, Genre $genre): JsonResponse
    {
        $genre->update($request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'slug' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('genres')->ignore($genre)],
        ]));

        return response()->json($genre);
    }

    public function destroy(Genre $genre): JsonResponse
    {
        $genre->delete();

        return response()->json(['message' => 'Genre deleted.']);
    }
}
