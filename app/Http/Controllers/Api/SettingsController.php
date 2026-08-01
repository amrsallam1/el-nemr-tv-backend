<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __invoke(string $code): JsonResponse
    {
        $overrides = AppSetting::query()
            ->where('is_public', true)
            ->pluck('value', 'key')
            ->all();

        return response()->json(array_replace(
            config('easyplex.public_settings'),
            $overrides,
            ['telegram_url' => 'https://t.me/Elnemr_11222'],
        ));
    }
}
