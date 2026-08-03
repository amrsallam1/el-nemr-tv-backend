<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InternalContentResetController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $configuredToken = (string) config('services.content_worker.sync_token');
        $providedToken = (string) $request->bearerToken();
        abort_unless($configuredToken !== '' && $providedToken !== '' && hash_equals($configuredToken, $providedToken), 403);

        $validated = $request->validate([
            'confirmation' => ['required', 'string', 'in:DELETE ALL MEDIA'],
        ]);

        $deleted = DB::transaction(fn () => Media::withTrashed()->forceDelete(), 3);
        Cache::flush();

        return response()->json([
            'status' => 'success',
            'deleted_media' => $deleted,
            'confirmation' => $validated['confirmation'],
        ]);
    }
}
