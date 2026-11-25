-- Undo 002: puts the "TEMP FULL ACCESS" policy back.
-- WARNING: this re-opens anime to inserts/updates/deletes from anyone with the
-- publishable key. Only for rolling back; don't leave it in place.

DROP POLICY IF EXISTS "TEMP FULL ACCESS" ON public.anime;
CREATE POLICY "TEMP FULL ACCESS" ON public.anime
    FOR ALL TO anon USING (true) WITH CHECK (true);
