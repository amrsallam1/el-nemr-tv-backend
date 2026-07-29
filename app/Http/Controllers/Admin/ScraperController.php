<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;

class ScraperController extends Controller
{
    public function run(): RedirectResponse
    {
        $exitCode = Artisan::call('scraper:run');
        $output = trim(Artisan::output());
        $message = $output !== '' ? $output : 'تعذر تشغيل السكربت.';
        $status = $exitCode === 0 ? 'success' : 'error';

        if ($exitCode !== 0 && str_contains(strtolower($output), 'scraper script not found')) {
            $message = $output."\n\nتأكد أن ملف `scraper/scraper.js` موجود داخل الريبو المنشور على Railway، وأن آخر deploy تم بنجاح.";
        }

        return back()
            ->with($status, $message !== '' ? $message : 'تم تشغيل السكربت بنجاح.')
            ->with('scraper_output', $output);
    }
}
