<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'tmdb' => [
        'key' => env('TMDB_API_KEY'),
        'access_token' => env('TMDB_ACCESS_TOKEN'),
    ],

    'movie_sync' => [
        'enabled' => env('MOVIE_SYNC_ENABLED', false),
        'daily_at' => env('MOVIE_SYNC_DAILY_AT', '03:00'),
        'timezone' => env('MOVIE_SYNC_TIMEZONE', 'Africa/Cairo'),
        'max_movies' => (int) env('MOVIE_SYNC_MAX_MOVIES', env('MAX_MOVIES', 50)),
        'max_allowed_movies' => 500,
        'max_pages' => (int) env('MOVIE_SYNC_MAX_PAGES', 25),
        'language' => env('TMDB_LANGUAGE', 'ar'),
        'tmdb_timeout_seconds' => (int) env('TMDB_TIMEOUT_SECONDS', 15),
        'stream_timeout_seconds' => (int) env('STREAM_TIMEOUT_SECONDS', 7),
        'retries' => (int) env('MOVIE_SYNC_RETRIES', 3),
        'lock_seconds' => (int) env('MOVIE_SYNC_LOCK_SECONDS', 21600),
        'allow_adult' => env('MOVIE_SYNC_ALLOW_ADULT', false),
        'require_stream' => env('MOVIE_SYNC_REQUIRE_STREAM', true),
        'csv_path' => env('MOVIE_SYNC_CSV_PATH', 'movie-sync/latest.csv'),
        'stream_sources' => [
            'https://vidsrc.xyz/embed/movie/{tmdb_id}',
            'https://vidsrc.in/embed/movie/{tmdb_id}',
            'https://vidsrc.cc/embed/movie/{tmdb_id}',
            'https://vidsrc.to/embed/movie/{tmdb_id}',
            'https://vidsrc.net/embed/movie/{tmdb_id}',
            'https://2embed.cc/embed/movie/{tmdb_id}',
            'https://multiembed.movie/?video_id={tmdb_id}&tmdb=1',
        ],
    ],

    'firebase' => [
        'credentials_json' => env('FIREBASE_CREDENTIALS_JSON'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'topic' => env('FIREBASE_TOPIC', 'all'),
    ],

    'content_worker' => [
        'enabled' => env('CONTENT_SYNC_ENABLED', false),
        'url' => env('CONTENT_WORKER_URL', 'https://akwam-stream-fetcher.meroo3292.workers.dev/'),
        'catalog_origin' => env('CONTENT_CATALOG_ORIGIN', 'https://akwam.it'),
        'allowed_source_hosts' => array_values(array_filter(array_map('trim', explode(',', env('CONTENT_ALLOWED_SOURCE_HOSTS', 'akwam.it,www.akwam.it'))))),
        'allowed_media_host_suffixes' => array_values(array_filter(array_map('trim', explode(',', env('CONTENT_ALLOWED_MEDIA_HOST_SUFFIXES', 'downet.net'))))),
        'playback_user_agent' => env('CONTENT_PLAYBACK_USER_AGENT', 'Mozilla/5.0 (Linux; Android 10; El-Nemr-TV) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36'),
        'daily_at' => env('CONTENT_SYNC_DAILY_AT', '04:00'),
        'timezone' => env('CONTENT_SYNC_TIMEZONE', 'Africa/Cairo'),
        'pages' => (int) env('CONTENT_SYNC_PAGES', 2),
        'limit' => (int) env('CONTENT_SYNC_LIMIT', 50),
        'max_pages' => (int) env('CONTENT_SYNC_MAX_PAGES', 10),
        'max_items' => (int) env('CONTENT_SYNC_MAX_ITEMS', 200),
        'timeout_seconds' => (int) env('CONTENT_WORKER_TIMEOUT_SECONDS', 20),
        'retries' => (int) env('CONTENT_WORKER_RETRIES', 2),
        'lock_seconds' => (int) env('CONTENT_SYNC_LOCK_SECONDS', 3600),
        'playback_cache_seconds' => (int) env('CONTENT_PLAYBACK_CACHE_SECONDS', 120),
        'sync_token' => env('CONTENT_SYNC_TOKEN'),
    ],

    'scraper' => [
        'path' => env('SCRAPER_SCRIPT_PATH'),
        'cwd' => env('SCRAPER_WORKDIR'),
    ],

];
