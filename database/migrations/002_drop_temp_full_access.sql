-- 002: remove the "TEMP FULL ACCESS" policy on anime (Build 3 security).
--
-- That policy let anyone holding the Supabase publishable (anon) key insert, update
-- and delete anime through Supabase's own REST API, bypassing this app entirely.
-- Anime is now only changed through api.php, which requires an admin
-- (app/Api/Middleware/AdminWriteMiddleware.php).
--
-- The read-only "Signed-out Access" policy (SELECT for anon) is kept.
-- Safe to run twice. Undo: 002_drop_temp_full_access.down.sql

DROP POLICY IF EXISTS "TEMP FULL ACCESS" ON public.anime;
