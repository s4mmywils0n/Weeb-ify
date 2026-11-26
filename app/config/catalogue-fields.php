<?php

declare(strict_types=1);

/*
 * The anime and manga columns a client may send in POST / PUT / PATCH (the allowlist),
 * and the ones that must be non-empty. Shared by api.php (which enforces it) and the
 * admin page (which builds its form from it), so the two can never disagree.
 *
 * Column names are the database's snake_case names.
 */
return [
    'anime' => [
        'fields'   => [
            'anime_id', 'slug', 'image', 'title', 'synopsis',
            'age_rating', 'created_at', 'end_date', 'episode_count',
            'popularity_rank', 'show_type', 'status', 'kitsu_url',
        ],
        'required' => ['title'],
    ],
    'manga' => [
        'fields'   => [
            'manga_id', 'slug', 'image', 'title', 'synopsis', 'age_rating',
            'created_at', 'end_date', 'chapter_count', 'volume_count',
            'popularity_rank', 'manga_type', 'serialization', 'status', 'kitsu_url',
        ],
        'required' => ['title'],
    ],
];
