<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('source')->nullable()->index();
            $table->string('source_id')->nullable()->index();
            $table->text('source_url')->nullable();
            $table->unique(['source', 'type', 'source_id'], 'media_source_identity_unique');
        });

        Schema::table('streams', function (Blueprint $table) {
            $table->text('source_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('streams', function (Blueprint $table) {
            $table->dropColumn('source_url');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->dropUnique('media_source_identity_unique');
            $table->dropColumn(['source', 'source_id', 'source_url']);
        });
    }
};
