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
                'المحتوى' => Media::count(),
                'المنشور' => Media::where('is_published', true)->count(),
                'المستخدمون' => User::where('role', 'user')->count(),
                'التصنيفات' => Genre::count(),
            ],
            'latest' => Media::latest()->limit(8)->get(),
        ]);
    }
}
