<?php

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncEgyptianSeries extends Command
{
    protected $signature = 'series:sync-egyptian {--limit=20}';
    protected $description = 'Import Egyptian TMDB series, seasons and episodes';

    public function handle(): int
    {
        $limit = min(50, max(1, (int) $this->option('limit')));
        $key = (string) config('services.tmdb.key');
        if ($key === '') { $this->error('TMDB_API_KEY is missing.'); return self::FAILURE; }
        $lock = Cache::lock('series:sync-egyptian', 21600);
        if (! $lock->get()) { $this->warn('Series import is already running.'); return self::SUCCESS; }
        try {
            $created = 0; $seasons = 0; $episodes = 0;
            for ($page = 1; $page <= 10 && $created < $limit; $page++) {
                $list = Http::acceptJson()->timeout(20)->get('https://api.themoviedb.org/3/discover/tv', [
                    'api_key' => $key, 'language' => 'ar-SA', 'page' => $page,
                    'with_origin_country' => 'EG', 'with_original_language' => 'ar', 'sort_by' => 'popularity.desc',
                ]);
                foreach ((array) $list->json('results') as $item) {
                    if ($created >= $limit) break;
                    $id = (string) ($item['id'] ?? '');
                    if ($id === '' || Media::withTrashed()->where('type', 'series')->where('tmdb_id', $id)->exists()) continue;
                    $detail = Http::acceptJson()->timeout(20)->get('https://api.themoviedb.org/3/tv/'.$id, ['api_key'=>$key,'language'=>'ar-SA']);
                    if (! $detail->successful()) continue;
                    $show = $detail->json(); $title = trim((string) ($show['name'] ?? ''));
                    if ($title === '') continue;
                    DB::transaction(function () use ($show, $id, $title, $key, &$seasons, &$episodes) {
                        $media = Media::create([
                            'type'=>'series','title'=>$title,'name'=>$title,'slug'=>Str::limit(Str::slug($title) ?: 'series',240,'').'-'.$id,
                            'tmdb_id'=>$id,'overview'=>$show['overview'] ?? null,
                            'poster_path'=>!empty($show['poster_path']) ? 'https://image.tmdb.org/t/p/w500'.$show['poster_path'] : null,
                            'backdrop_path'=>!empty($show['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280'.$show['backdrop_path'] : null,
                            'release_date'=>$show['first_air_date'] ?? null,'vote_average'=>(float)($show['vote_average'] ?? 0),'is_published'=>true,
                            'metadata'=>['automatic_import'=>true,'source'=>'egyptian_series'],
                        ]);
                        foreach ((array) ($show['seasons'] ?? []) as $seasonInfo) {
                            $n = (int) ($seasonInfo['season_number'] ?? -1); if ($n < 0) continue;
                            $season = $media->seasons()->create(['season_number'=>$n,'name'=>$seasonInfo['name'] ?? ('Season '.$n),'overview'=>$seasonInfo['overview'] ?? null,'poster_path'=>!empty($seasonInfo['poster_path']) ? 'https://image.tmdb.org/t/p/w500'.$seasonInfo['poster_path'] : null,'air_date'=>$seasonInfo['air_date'] ?? null]);
                            $seasons++;
                            $seasonData = Http::acceptJson()->timeout(20)->get('https://api.themoviedb.org/3/tv/'.$id.'/season/'.$n, ['api_key'=>$key,'language'=>'ar-SA'])->json('episodes') ?? [];
                            foreach ($seasonData as $episodeInfo) { $num=(int)($episodeInfo['episode_number'] ?? 0); if($num<1)continue; $season->episodes()->create(['episode_number'=>$num,'name'=>$episodeInfo['name'] ?? ('Episode '.$num),'overview'=>$episodeInfo['overview'] ?? null,'still_path'=>!empty($episodeInfo['still_path']) ? 'https://image.tmdb.org/t/p/w780'.$episodeInfo['still_path'] : null,'air_date'=>$episodeInfo['air_date'] ?? null,'runtime'=>isset($episodeInfo['runtime'])?(int)$episodeInfo['runtime']:null]); $episodes++; }
                        }
                    });
                    $created++; $this->line('Imported: '.$title);
                }
            }
            $this->info("Imported {$created} series, {$seasons} seasons, {$episodes} episodes."); return self::SUCCESS;
        } finally { $lock->release(); }
    }
}
