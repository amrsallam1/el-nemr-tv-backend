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

        if ($exitCode !== 0) {
            return back()->withErrors([
                'scraper' => $output !== '' ? $output : 'تعذر تشغيل السكربت.',
            ]);
        }

        return back()->with('success', $output !== '' ? $output : 'تم تشغيل السكربت بنجاح.');
    }
}
