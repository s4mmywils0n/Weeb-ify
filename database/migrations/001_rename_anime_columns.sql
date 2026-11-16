-- =============================================================================
-- Migration 001: rename the anime columns that contain spaces to snake_case
--
--   "age rating"      -> age_rating
--   "created at"      -> created_at
--   "end date"        -> end_date
--   "episode count"   -> episode_count
--   "popularity rank" -> popularity_rank
--   "show type"       -> show_type
--
-- Why: names with spaces must be double-quoted everywhere (SQL, PHP, JavaScript
-- record["show type"], XML), and the seed script, API and pages had drifted apart.
-- snake_case also matches the favorites table (episode_count, show_type).
--
-- Only the names change: no data, types, indexes or policies are touched.
-- Safe to run more than once: a column that was already renamed is skipped.
-- Runs in one transaction: either every rename happens or none do.
-- Undo with: database/migrations/001_rename_anime_columns.down.sql
--
-- IMPORTANT: the PHP code expects the NEW names, so run this before using the
-- updated code (Supabase dashboard > SQL editor, or psql -f).
-- =============================================================================

BEGIN;

DO $$
DECLARE
    pair text[];
BEGIN
    FOREACH pair SLICE 1 IN ARRAY ARRAY[
        ['age rating',      'age_rating'],
        ['created at',      'created_at'],
        ['end date',        'end_date'],
        ['episode count',   'episode_count'],
        ['popularity rank', 'popularity_rank'],
        ['show type',       'show_type']
    ]
    LOOP
        IF EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = 'anime' AND column_name = pair[1]
        ) THEN
            EXECUTE format('ALTER TABLE public.anime RENAME COLUMN %I TO %I', pair[1], pair[2]);
            RAISE NOTICE 'renamed "%" to %', pair[1], pair[2];
        ELSE
            RAISE NOTICE 'skipped "%" (not found, probably already renamed)', pair[1];
        END IF;
    END LOOP;
END
$$;

COMMIT;
