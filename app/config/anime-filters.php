<?php

declare(strict_types=1);

/*
 * Filter, sort and pagination options for the anime list.
 * Used by BOTH api.php (to build the SQL) and app/views/anime.php (to build the controls),
 * so the two can never disagree.
 *
 * Only values listed here are accepted, so nothing from the URL is ever put into SQL directly.
 *
 * NOTE: the Supabase columns have spaces in their names ("show type", "age rating", ...),
 * so they must be wrapped in double quotes in SQL.
 */
return [
    // ?showtype=TV
    'showtype' => [
        'label'   => 'Show type',
        'column'  => '"show type"',
        'options' => [
            'TV'      => 'TV',
            'movie'   => 'Movie',
            'OVA'     => 'OVA',
            'ONA'     => 'ONA',
            'special' => 'Special',
            'music'   => 'Music',
        ],
    ],

    // ?agerating=PG
    'agerating' => [
        'label'   => 'Age rating',
        'column'  => '"age rating"',
        'options' => [
            'G'  => 'G — General audiences',
            'PG' => 'PG — Parental guidance',
            'R'  => 'R — 17+',
        ],
    ],

    // ?category[]=Action&category[]=Comedy   (checkboxes; an anime must have ALL ticked categories)
    // "genres" is a text[] column filled by database/Category.php.
    'category' => [
        'label'   => 'Categories',
        'column'  => 'genres',
        'options' => [
            'Action', 'Adventure', 'Comedy', 'Drama', 'Fantasy', 'Horror',
            'Isekai', 'Mecha', 'Music', 'Mystery', 'Psychological', 'Romance',
            'Seinen', 'Shounen', 'Slice of Life', 'Sports', 'Supernatural', 'Thriller',
        ],
    ],

    // ?limit=10
    'limit' => [
        'options' => [5, 10, 20],
        'default' => 20,
    ],

    // ?sort=title_asc
    // Cursor pagination needs, for each sort:
    //   expr - the value to order by (COALESCE keeps missing values at the end, so cursors still work)
    //   cast - its SQL type, used when comparing against the cursor
    //   dir  - ASC or DESC (id is used as a tie-breaker in the same direction)
    'sort' => [
        'default'         => ['label' => 'Default',                                'expr' => 'id',                                                    'cast' => 'bigint',    'dir' => 'ASC'],
        'title_asc'       => ['label' => 'Title: A → Z',                           'expr' => "COALESCE(LOWER(title), '')",                            'cast' => 'text',      'dir' => 'ASC'],
        'title_desc'      => ['label' => 'Title: Z → A',                           'expr' => "COALESCE(LOWER(title), '')",                            'cast' => 'text',      'dir' => 'DESC'],
        'created_asc'     => ['label' => 'Date added: oldest first',               'expr' => "COALESCE(\"created at\", 'infinity'::timestamp)",        'cast' => 'timestamp', 'dir' => 'ASC'],
        'created_desc'    => ['label' => 'Date added: newest first',               'expr' => "COALESCE(\"created at\", '-infinity'::timestamp)",       'cast' => 'timestamp', 'dir' => 'DESC'],
        'popularity_asc'  => ['label' => 'Popularity rank: ascending (#1 first)',  'expr' => 'COALESCE("popularity rank", 2147483647)',               'cast' => 'bigint',    'dir' => 'ASC'],
        'popularity_desc' => ['label' => 'Popularity rank: descending',            'expr' => 'COALESCE("popularity rank", -1)',                       'cast' => 'bigint',    'dir' => 'DESC'],
    ],
];
