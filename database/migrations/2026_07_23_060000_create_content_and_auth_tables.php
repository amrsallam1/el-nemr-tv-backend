<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->index();
            $table->string('avatar')->nullable();
            $table->boolean('is_premium')->default(false)->index();
            $table->timestamp('premium_until')->nullable();
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::create('access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('android');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // movie, series, anime, live
            $table->string('title');
            $table->string('name')->nullable();
            $table->string('slug')->unique();
            $table->string('tmdb_id')->nullable()->index();
            $table->string('imdb_id')->nullable()->index();
            $table->text('overview')->nullable();
            $table->string('poster_path')->nullable();
            $table->string('backdrop_path')->nullable();
            $table->string('preview_path')->nullable();
            $table->date('release_date')->nullable();
            $table->unsignedSmallInteger('runtime')->nullable();
            $table->decimal('vote_average', 4, 2)->default(0);
            $table->unsignedBigInteger('views')->default(0)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_recommended')->default(false)->index();
            $table->boolean('is_pinned')->default(false)->index();
            $table->boolean('is_premium')->default(false)->index();
            $table->boolean('is_published')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('genre_media', function (Blueprint $table) {
            $table->foreignId('genre_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->primary(['genre_id', 'media_id']);
        });

        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->unsignedSmallInteger('season_number');
            $table->string('name')->nullable();
            $table->text('overview')->nullable();
            $table->string('poster_path')->nullable();
            $table->date('air_date')->nullable();
            $table->timestamps();
            $table->unique(['media_id', 'season_number']);
        });

        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('episode_number');
            $table->string('name');
            $table->text('overview')->nullable();
            $table->string('still_path')->nullable();
            $table->date('air_date')->nullable();
            $table->unsignedSmallInteger('runtime')->nullable();
            $table->boolean('is_premium')->default(false);
            $table->timestamps();
            $table->unique(['season_id', 'episode_number']);
        });

        Schema::create('streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->nullable()->constrained('media')->cascadeOnDelete();
            $table->foreignId('episode_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name')->default('Server 1');
            $table->text('url');
            $table->string('quality')->nullable();
            $table->string('language')->nullable();
            $table->json('headers')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['media_id', 'episode_id']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'media_id']);
        });

        Schema::create('watch_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->nullable()->constrained('media')->cascadeOnDelete();
            $table->foreignId('episode_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position_seconds')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'media_id', 'episode_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_progress');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('streams');
        Schema::dropIfExists('episodes');
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('genre_media');
        Schema::dropIfExists('media');
        Schema::dropIfExists('genres');
        Schema::dropIfExists('access_tokens');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'avatar', 'is_premium', 'premium_until', 'is_active']);
        });
    }
};
