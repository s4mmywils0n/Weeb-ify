<?php

declare(strict_types=1);

/*
 * Filter, sort and pagination options for the manga list.
 * Used by both api.php (to build the SQL) and app/views/manga.php (to build the controls).
 */
return [
    // ?mangatype=manga
    'mangatype' => [
        'label'   => 'Manga type',
        'column'  => 'manga_type',
        'options' => [
            'manga'    => 'Manga',
            'novel'    => 'Novel',
            'one-shot' => 'One-shot',
            'manhua'   => 'Manhua',
            'manhwa'   => 'Manhwa',
            'oel'      => 'OEL',
        ],
    ],

    // ?agerating=PG
    'agerating' => [
        'label'   => 'Age rating',
        'column'  => 'age_rating',
        'options' => [
            'G'   => 'G',
            'PG'  => 'PG',
            'R'   => 'R',
            'R18' => 'R18',
        ],
    ],

    // ?category[]=Action&category[]=Comedy (a manga must have all ticked categories)
    'category' => [
        'label'   => 'Categories',
        'column'  => 'genres',
        'options' => [
            'Action', 'Adventure', 'Comedy', 'Drama', 'Fantasy', 'Horror',
            'Mystery', 'Romance', 'Sci-Fi', 'Slice of Life', 'Sports',
            'Supernatural', 'Thriller', 'Mecha', 'Music', 'Psychological',
            'School', 'Shounen', 'Seinen', 'Isekai',
        ],
    ],

    // ?limit=20
    'limit' => [
        'options' => [10, 20, 50, 100],
        'default' => 20,
    ],

    'sort' => [
        'default'         => ['label' => 'Default',                                'expr' => 'id',                                              'cast' => 'bigint',    'dir' => 'ASC'],
        'title_asc'       => ['label' => 'Title: A → Z',                           'expr' => "COALESCE(LOWER(title), '')",                      'cast' => 'text',      'dir' => 'ASC'],
        'title_desc'      => ['label' => 'Title: Z → A',                           'expr' => "COALESCE(LOWER(title), '')",                      'cast' => 'text',      'dir' => 'DESC'],
        'created_asc'     => ['label' => 'Date added: oldest first',               'expr' => "COALESCE(created_at, 'infinity'::timestamp)",      'cast' => 'timestamp', 'dir' => 'ASC'],
        'created_desc'    => ['label' => 'Date added: newest first',               'expr' => "COALESCE(created_at, '-infinity'::timestamp)",     'cast' => 'timestamp', 'dir' => 'DESC'],
        'popularity_asc'  => ['label' => 'Popularity rank: ascending (#1 first)',  'expr' => 'COALESCE(popularity_rank, 2147483647)',             'cast' => 'bigint',    'dir' => 'ASC'],
        'popularity_desc' => ['label' => 'Popularity rank: descending',            'expr' => 'COALESCE(popularity_rank, -1)',                     'cast' => 'bigint',    'dir' => 'DESC'],
    ],
];
