<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;

class ScraperController extends Controller
{
    public function run(): RedirectResponse
    {
        $exitCode = Artisan::call('movies:sync-popular');
        $output = trim(Artisan::output());
        $status = $exitCode === 0 ? 'success' : 'error';
        $fallback = $exitCode === 0
            ? 'تم تحديث الأفلام بنجاح.'
            : 'تعذر تحديث الأفلام. راجع سجل التشغيل.';

        return back()
            ->with($status, $output !== '' ? $output : $fallback)
            ->with('scraper_output', $output);
    }
}
