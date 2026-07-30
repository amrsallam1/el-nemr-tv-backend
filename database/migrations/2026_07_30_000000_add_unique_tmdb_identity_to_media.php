<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicates = DB::table('media')
            ->whereNotNull('tmdb_id')
            ->select('type', 'tmdb_id')
            ->groupBy('type', 'tmdb_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException('Duplicate type/tmdb_id rows exist in media. Merge them before running this migration.');
        }

        Schema::table('media', function (Blueprint $table) {
            $table->unique(['type', 'tmdb_id'], 'media_type_tmdb_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropUnique('media_type_tmdb_id_unique');
        });
    }
};
