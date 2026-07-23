<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE media ALTER COLUMN poster_path TYPE TEXT');
        DB::statement('ALTER TABLE media ALTER COLUMN backdrop_path TYPE TEXT');
        DB::statement('ALTER TABLE media ALTER COLUMN preview_path TYPE TEXT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE media ALTER COLUMN poster_path TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE media ALTER COLUMN backdrop_path TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE media ALTER COLUMN preview_path TYPE VARCHAR(255)');
    }
};
