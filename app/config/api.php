<?php

declare(strict_types=1);

/*
 * Settings for the JSON/XML/YAML API (api.php).
 *
 * resources: the only tables the API exposes. Anything not listed here is a 404,
 * so the "user" table (password hashes) can never be reached through the API.
 *   table    - real table name (quoted in SQL, so names with spaces are fine)
 *   fields   - columns a client may send in POST/PUT/PATCH (the allowlist)
 *   required - fields that must be non-empty strings on POST and PUT
 */
$env = is_file(dirname(__DIR__, 2) . '/.env')
    ? (parse_ini_file(dirname(__DIR__, 2) . '/.env', false, INI_SCANNER_RAW) ?: [])
    : [];

return [
    'resources' => [
        'anime' => [
            'table'    => 'anime',
            // Real column names (see database/schema.sql)
            'fields'   => [
                'anime_id', 'slug', 'image', 'title', 'synopsis',
                'age_rating', 'created_at', 'end_date', 'episode_count',
                'popularity_rank', 'show_type', 'status', 'kitsu_url',
            ],
            'required' => ['title'],
        ],
        // Kitsu-imported manga table (same columns main's api.php and manga pages use)
        'manga' => [
            'table'    => 'manga',
            'fields'   => [
                'manga_id', 'slug', 'image', 'title', 'synopsis', 'agerating',
                'createdat', 'enddate', 'chaptercount', 'volumecount',
                'popularityrank', 'mangatype', 'serialization', 'status', 'kitsu_url',
            ],
            'required' => ['title'],
        ],
    ],

    // Page size for plain ?page=N lists (anime has its own options in anime-filters.php)
    'items_per_page' => 20,

    // Largest request body accepted, in bytes (bigger -> 413)
    'max_body_bytes' => 64 * 1024,

    // Browser origins allowed to call the API from another site (CORS).
    // The app's own pages are same-origin and don't need to be listed.
    // Override in .env with:  cors_origins=http://localhost:5173,https://example.com
    'cors_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', $env['cors_origins'] ?? 'http://localhost,http://127.0.0.1')
    ))),

    // One line per request + full error details. storage/ is blocked from the web.
    'log_file' => dirname(__DIR__, 2) . '/storage/logs/api.log',
];
