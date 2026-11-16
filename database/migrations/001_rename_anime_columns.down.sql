-- =============================================================================
-- Undo migration 001: put the spaces back in the anime column names.
-- Only needed to roll back. The current PHP code expects the snake_case names,
-- so it will stop working until the code is rolled back too.
-- Safe to run more than once.
-- =============================================================================

BEGIN;

DO $$
DECLARE
    pair text[];
BEGIN
    FOREACH pair SLICE 1 IN ARRAY ARRAY[
        ['age_rating',      'age rating'],
        ['created_at',      'created at'],
        ['end_date',        'end date'],
        ['episode_count',   'episode count'],
        ['popularity_rank', 'popularity rank'],
        ['show_type',       'show type']
    ]
    LOOP
        IF EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = 'anime' AND column_name = pair[1]
        ) THEN
            EXECUTE format('ALTER TABLE public.anime RENAME COLUMN %I TO %I', pair[1], pair[2]);
        END IF;
    END LOOP;
END
$$;

COMMIT;
