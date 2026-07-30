<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $mediaId = DB::table('media')
            ->where('type', 'movie')
            ->where('tmdb_id', '155')
            ->whereNull('deleted_at')
            ->value('id');

        if (! $mediaId || DB::table('streams')->where('media_id', $mediaId)->where('is_active', true)->exists()) {
            return;
        }

        DB::table('streams')->insert([
            'media_id' => $mediaId,
            'episode_id' => null,
            'name' => 'Server 1',
            'url' => 'https://vidsrc.in/embed/movie/155',
            'quality' => null,
            'language' => null,
            'embed' => true,
            'headers' => null,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('streams')
            ->where('url', 'https://vidsrc.in/embed/movie/155')
            ->delete();
    }
};
