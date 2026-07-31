<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $data = Cache::remember('admin-dashboard:v2', now()->addSeconds(60), function () {
            $counts = Media::query()->selectRaw(
                'COUNT(*) AS total,
                 SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) AS movies,
                 SUM(CASE WHEN type IN (?, ?) THEN 1 ELSE 0 END) AS shows,
                 SUM(CASE WHEN is_published = ? THEN 1 ELSE 0 END) AS published,
                 SUM(CASE WHEN is_featured = ? THEN 1 ELSE 0 END) AS featured,
                 SUM(CASE WHEN views > 0 THEN 1 ELSE 0 END) AS trending,
                 SUM(CASE WHEN is_pinned = ? THEN 1 ELSE 0 END) AS pinned,
                 SUM(CASE WHEN is_recommended = ? THEN 1 ELSE 0 END) AS recommended',
                ['movie', 'series', 'anime', true, true, true, true]
            )->first();

            return [
                'stats' => [
                    'إجمالي المحتوى' => (int) $counts->total,
                    'الأفلام' => (int) $counts->movies,
                    'المسلسلات والأنمي' => (int) $counts->shows,
                    'المستخدمون' => User::where('role', 'user')->count(),
                ],
                'sectionStats' => [
                    'المنشور' => (int) $counts->published,
                    'Featured' => (int) $counts->featured,
                    'Trending' => (int) $counts->trending,
                    'Pinned / Top10' => (int) $counts->pinned,
                    'Recommended' => (int) $counts->recommended,
                    'التصنيفات' => Genre::count(),
                ],
                'latest' => Media::latest()->limit(8)->get(),
            ];
        });

        // Discard stale/corrupt dashboard cache entries from older releases.
        if (! isset($data['latest']) || ! is_iterable($data['latest'])) {
            $data['latest'] = Media::latest()->limit(8)->get();
        }

        return view('admin.dashboard', $data);
    }
}
