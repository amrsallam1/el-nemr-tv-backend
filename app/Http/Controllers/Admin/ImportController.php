<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\TmdbService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ImportController extends Controller
{
    public function __construct(private readonly TmdbService $tmdb) {}

    public function create(): View
    {
        return view('admin.media.import');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $handle = fopen($request->file('csv')->getRealPath(), 'rb');
        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);

            return back()->withErrors(['csv' => 'ملف CSV فارغ أو غير صالح.']);
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $headers = array_map(fn ($header) => Str::snake(trim((string) $header)), $headers);
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $values = array_pad($values, count($headers), null);
            $rows[] = array_combine($headers, array_slice($values, 0, count($headers)));
        }

        $created = $updated = $streams = $failed = 0;
        // Large imports must not make one synchronous request perform 100 remote API calls.
        $enrichTitles = count($rows) <= 20;
        foreach ($rows as $row) {
            try {
                $title = trim((string) ($row['title'] ?? ''));
                $type = strtolower(trim((string) ($row['type'] ?? 'movie')));
                if ($title === '' || ! in_array($type, ['movie', 'series', 'anime', 'live'], true)) {
                    throw new \InvalidArgumentException;
                }
                $tmdbId = trim((string) ($row['tmdb_id'] ?? '')) ?: null;
                $metadata = ($enrichTitles || $tmdbId) ? $this->tmdb->find($type, $title, $tmdbId) : [];
                $tmdbId = $tmdbId ?: ($metadata['tmdb_id'] ?? null);
                $slug = Str::slug($title);
                if ($slug === '') {
                    $slug = 'media-'.substr(sha1($type.'|'.$title), 0, 12);
                }
                $slug .= $tmdbId ? '-'.$tmdbId : '';
                $media = $tmdbId
                    ? Media::withTrashed()->where('type', $type)->where('tmdb_id', $tmdbId)->first()
                    : Media::withTrashed()->where('slug', $slug)->first();
                $payload = [
                    'type' => $type, 'title' => $title, 'name' => $title, 'slug' => $slug, 'tmdb_id' => $tmdbId,
                    'overview' => trim((string) ($row['overview'] ?? $row['description'] ?? '')) ?: ($metadata['overview'] ?? null),
                    'poster_path' => trim((string) ($row['poster_path'] ?? $row['poster_url'] ?? '')) ?: ($metadata['poster_path'] ?? null),
                    'backdrop_path' => trim((string) ($row['backdrop_path'] ?? $row['backdrop_url'] ?? '')) ?: ($metadata['backdrop_path'] ?? null),
                    'release_date' => $this->releaseDate($row['release_date'] ?? $row['year'] ?? null) ?: ($metadata['release_date'] ?? null),
                    'vote_average' => $metadata['vote_average'] ?? 0,
                    'is_featured' => $this->boolean($row['featured'] ?? $row['is_featured'] ?? false),
                    'is_published' => $this->boolean($row['published'] ?? $row['is_published'] ?? true),
                ];
                if ($media) {
                    $media->restore();
                    $media->update($payload);
                    $updated++;
                } else {
                    $media = Media::create($payload);
                    $created++;
                }
                $url = trim((string) ($row['stream_url'] ?? $row['url'] ?? ''));
                if ($url !== '') {
                    $streamData = ['name' => trim((string) ($row['server'] ?? 'Server 1')) ?: 'Server 1', 'url' => $url, 'quality' => trim((string) ($row['quality'] ?? '')) ?: null, 'language' => trim((string) ($row['language'] ?? '')) ?: null, 'embed' => $this->boolean($row['embed'] ?? false), 'is_active' => true];
                    $stream = $media->streams()->where('url', $url)->first();
                    if ($stream) {
                        $stream->update($streamData);
                    } else {
                        $media->streams()->create($streamData);
                    }
                    $streams++;
                }
            } catch (\Throwable) {
                $failed++;
            }
        }

        return redirect()->route('admin.media.index')->with('success', "تم الاستيراد: {$created} جديد، {$updated} محدث، {$streams} رابط تشغيل، {$failed} صف به خطأ.");
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? ((int) $value === 1);
    }

    private function releaseDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return preg_match('/^\d{4}$/', $value) ? $value.'-01-01' : $value;
    }
}
