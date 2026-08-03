<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorkerContentSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalContentSyncController extends Controller
{
    public function __invoke(Request $request, WorkerContentSyncService $sync): JsonResponse
    {
        $configuredToken = (string) config('services.content_worker.sync_token');
        $providedToken = (string) $request->bearerToken();
        abort_unless(
            $configuredToken !== '' && $providedToken !== '' && hash_equals($configuredToken, $providedToken),
            403,
        );

        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'in:movies,series,all'],
            'pages' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'dry_run' => ['sometimes', 'boolean'],
            'years' => ['sometimes', 'array', 'max:5'],
            'years.*' => ['integer', 'min:2000', 'max:2100'],
        ]);
        $types = ($validated['type'] ?? 'all') === 'all'
            ? ['movies', 'series']
            : [$validated['type']];
        $reports = [];
        foreach ($types as $type) {
            $reports[] = $sync->sync(
                $type,
                (int) ($validated['pages'] ?? 1),
                (int) ($validated['limit'] ?? 10),
                (bool) ($validated['dry_run'] ?? false),
                null,
                array_values(array_unique($validated['years'] ?? [])),
            );
        }

        return response()->json(['status' => 'success', 'reports' => $reports]);
    }
}
