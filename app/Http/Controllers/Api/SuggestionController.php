<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Suggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestionController extends Controller
{
    public function store(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $suggestion = Suggestion::query()->create([
            'title' => trim($data['title'] ?? '') ?: 'User',
            'message' => trim($data['message']),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'title' => $suggestion->title,
            'message' => $suggestion->message,
        ], 201);
    }
}
