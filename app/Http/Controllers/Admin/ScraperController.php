<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;

class ScraperController extends Controller
{
    public function run(): RedirectResponse
    {
        // Fifty movies can exceed an HTTP request timeout. Queue the command so
        // the admin receives an immediate response while Railway finishes it.
        Artisan::queue('movies:sync-popular', ['--force-unlock' => true]);

        return back()->with('success', 'بدأت مزامنة الأفلام في الخلفية. يمكنك مغادرة الصفحة بأمان.');
    }
}
