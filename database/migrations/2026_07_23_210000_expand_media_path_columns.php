<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->text('poster_path')->nullable()->change();
            $table->text('backdrop_path')->nullable()->change();
            $table->text('preview_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('poster_path')->nullable()->change();
            $table->string('backdrop_path')->nullable()->change();
            $table->string('preview_path')->nullable()->change();
        });
    }
};
