<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class NotificationController extends Controller
{
    public function create(FirebaseNotificationService $firebase): View
    {
        return view('admin.notifications.create', [
            'configured' => $firebase->configured(),
            'media' => Media::query()->where('is_published', true)->latest()->limit(100)->get(['id', 'title', 'name', 'type']),
        ]);
    }

    public function store(Request $request, FirebaseNotificationService $firebase): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:500'],
            'image' => ['nullable', 'url', 'max:2048'],
            'link' => ['nullable', 'url', 'max:2048'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
        ]);

        $media = empty($data['media_id']) ? null : Media::find($data['media_id']);
        $type = match ($media?->type) {
            'series' => '1',
            'anime' => '2',
            'live' => '3',
            'movie' => '0',
            default => 'custom',
        };

        try {
            $firebase->sendToAll([
                'type' => $type,
                'tmdb' => (string) ($media?->id ?? ''),
                'title' => $data['title'],
                'message' => $data['message'],
                'image' => (string) ($data['image'] ?? $media?->backdrop_path ?? ''),
                'link' => (string) ($data['link'] ?? ''),
            ]);
        } catch (Throwable $error) {
            report($error);

            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', 'تم إرسال الإشعار لكل مستخدمي التطبيق بنجاح.');
    }
}
