<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use App\Models\Media;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'إجمالي المحتوى' => Media::count(),
                'الأفلام' => Media::where('type', 'movie')->count(),
                'المسلسلات والأنمي' => Media::whereIn('type', ['series', 'anime'])->count(),
                'المستخدمون' => User::where('role', 'user')->count(),
            ],
            'sectionStats' => [
                'المنشور' => Media::where('is_published', true)->count(),
                'Featured' => Media::where('is_featured', true)->count(),
                'Trending' => Media::where('views', '>', 0)->count(),
                'Pinned / Top10' => Media::where('is_pinned', true)->count(),
                'Recommended' => Media::where('is_recommended', true)->count(),
                'التصنيفات' => Genre::count(),
            ],
            'latest' => Media::latest()->limit(8)->get(),
        ]);
    }
}
