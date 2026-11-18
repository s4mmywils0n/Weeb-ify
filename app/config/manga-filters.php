<?php

declare(strict_types=1);

//does the same thhing as anime filters but its mangas

return [
    'mangatype' => [
        'label' => 'Manga type',
        'options' => [
            'manga'     => 'Manga',
            'novel'     => 'Novel',
            'one-shot'  => 'One-shot',
            'manhua'    => 'Manhua',
            'manhwa'    => 'Manhwa',
            'oel'       => 'OEL',
        ],
    ],
    'agerating' => [
        'label' => 'Age rating',
        'options' => [
            'G'   => 'G',
            'PG'  => 'PG',
            'R'   => 'R',
            'R18' => 'R18',
        ],
    ],
    'sort' => [
        'label' => 'Sort by',
        'options' => [
            'title_asc'   => 'Title (A–Z)',
            'title_desc'  => 'Title (Z–A)',
            'createdat_asc'  => 'Created date (oldest first)',
            'createdat_desc' => 'Created date (newest first)',
            'popularityrank_asc'  => 'Popularity rank (most popular)',
            'popularityrank_desc' => 'Popularity rank (least popular)',
        ],
    ],
    'limit' => [
        'label' => 'Results per page',
        'options' => [10, 20, 50, 100],
    ],
    'category' => [
        'label' => 'Categories',
        'options' => [
            'Action', 'Adventure', 'Comedy', 'Drama', 'Fantasy', 'Horror',
            'Mystery', 'Romance', 'Sci-Fi', 'Slice of Life', 'Sports',
            'Supernatural', 'Thriller', 'Mecha', 'Music', 'Psychological',
            'School', 'Shounen', 'Seinen', 'Isekai',
        ],
    ],
];