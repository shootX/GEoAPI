-- WP-03 live catalog inventory. READ ONLY.
-- Run each statement as the catalog SELECT role (carapi_catalog_reader), not the migration owner.
-- Paste every result back, including errors. Do not print host, user, or password.
-- Do not run INSERT, UPDATE, DELETE, ALTER, migrate, or down.
-- If the catalog and platform databases are separate, run section A on the catalog database
-- and section B on the platform database. Run section C on the Laravel database.
-- A missing table or column is useful: paste the error and continue with the next statement.

-- 0. Who ran this, and when. Do not add connection settings.
SELECT current_user AS db_user,
       current_database() AS database_name,
       (now() AT TIME ZONE 'UTC') AS queried_at_utc;

-- A1. Schemas, tables, and owners.
SELECT n.nspname AS schema_name,
       c.relname AS table_name,
       pg_get_userbyid(c.relowner) AS owner
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname IN ('public', 'platform')
  AND c.relkind = 'r'
ORDER BY 1, 2;

-- A2. Columns for the catalog and platform tables the application reads.
SELECT table_schema, table_name, column_name, data_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_schema IN ('public', 'platform')
  AND table_name IN (
      'brands', 'models', 'model_markets', 'generations', 'modifications',
      'modification_specs', 'modification_equipment', 'model_photos', 'photos',
      'users', 'login_attempts', 'projects', 'api_keys', 'project_limits', 'widgets'
  )
ORDER BY table_schema, table_name, ordinal_position;

-- A3. Foreign keys.
SELECT tc.table_schema,
       tc.table_name,
       kcu.column_name,
       ccu.table_schema AS foreign_schema,
       ccu.table_name AS foreign_table,
       ccu.column_name AS foreign_column,
       tc.constraint_name
FROM information_schema.table_constraints tc
JOIN information_schema.key_column_usage kcu
  ON kcu.constraint_name = tc.constraint_name
 AND kcu.table_schema = tc.table_schema
JOIN information_schema.constraint_column_usage ccu
  ON ccu.constraint_name = tc.constraint_name
 AND ccu.table_schema = tc.table_schema
WHERE tc.constraint_type = 'FOREIGN KEY'
  AND tc.table_schema IN ('public', 'platform')
ORDER BY 1, 2, 3;

-- A4. Unique indexes and primary keys.
SELECT schemaname, tablename, indexname, indexdef
FROM pg_indexes
WHERE schemaname IN ('public', 'platform')
  AND indexdef ILIKE '%UNIQUE%'
ORDER BY 1, 2, 3;

-- A5. Triggers, including updated_at triggers added by 2026_10_20_210000_catalog_api_parity.
SELECT event_object_schema, event_object_table, trigger_name, action_timing, event_manipulation
FROM information_schema.triggers
WHERE event_object_schema IN ('public', 'platform')
ORDER BY 1, 2, 3;

-- A6. Live counts. One row per object.
SELECT 'brands' AS object_name, count(*) AS rows FROM public.brands
UNION ALL SELECT 'models', count(*) FROM public.models
UNION ALL SELECT 'generations', count(*) FROM public.generations
UNION ALL SELECT 'modifications', count(*) FROM public.modifications
UNION ALL SELECT 'modification_specs', count(*) FROM public.modification_specs
UNION ALL SELECT 'modification_equipment', count(*) FROM public.modification_equipment
UNION ALL SELECT 'photos', count(*) FROM public.photos
UNION ALL SELECT 'model_photos', count(*) FROM public.model_photos
ORDER BY 1;

-- A7. Coverage per brand.
SELECT b.id AS brand_id,
       b.slug,
       count(DISTINCT m.id) AS models,
       count(DISTINCT g.id) AS generation_rows,
       count(DISTINCT mod.id) AS modifications
FROM public.brands b
LEFT JOIN public.models m ON m.brand_id = b.id
LEFT JOIN public.generations g ON g.model_id = m.id
LEFT JOIN public.modifications mod ON mod.model_id = m.id
GROUP BY b.id, b.slug
ORDER BY b.slug;

-- A8. Coverage per model, including models with no generation.
SELECT m.id AS model_id,
       b.slug AS brand_slug,
       m.slug AS model_slug,
       count(DISTINCT g.id) AS generation_rows,
       count(DISTINCT mod.id) AS modifications,
       min(g.year_start) AS generation_year_start_min,
       max(g.year_end) AS generation_year_end_max
FROM public.models m
JOIN public.brands b ON b.id = m.brand_id
LEFT JOIN public.generations g ON g.model_id = m.id
LEFT JOIN public.modifications mod ON mod.model_id = m.id
GROUP BY m.id, b.slug, m.slug
ORDER BY b.slug, m.slug;

-- A9. Orphans and invalid year ranges.
SELECT 'models_missing_brand' AS check_name, count(*) AS rows
FROM public.models m
LEFT JOIN public.brands b ON b.id = m.brand_id
WHERE b.id IS NULL
UNION ALL
SELECT 'generations_missing_model', count(*)
FROM public.generations g
LEFT JOIN public.models m ON m.id = g.model_id
WHERE m.id IS NULL
UNION ALL
SELECT 'modifications_missing_model', count(*)
FROM public.modifications mod
LEFT JOIN public.models m ON m.id = mod.model_id
WHERE m.id IS NULL
UNION ALL
SELECT 'modifications_missing_generation', count(*)
FROM public.modifications mod
LEFT JOIN public.generations g ON g.id = mod.generation_id
WHERE mod.generation_id IS NOT NULL AND g.id IS NULL
UNION ALL
SELECT 'specs_missing_modification', count(*)
FROM public.modification_specs s
LEFT JOIN public.modifications mod ON mod.id = s.modification_id
WHERE mod.id IS NULL
UNION ALL
SELECT 'equipment_missing_modification', count(*)
FROM public.modification_equipment e
LEFT JOIN public.modifications mod ON mod.id = e.modification_id
WHERE mod.id IS NULL
UNION ALL
SELECT 'model_photos_missing_model', count(*)
FROM public.model_photos p
LEFT JOIN public.models m ON m.id = p.model_id
WHERE m.id IS NULL
UNION ALL
SELECT 'generations_year_end_before_start', count(*)
FROM public.generations
WHERE year_start IS NOT NULL AND year_end IS NOT NULL AND year_end < year_start
UNION ALL
SELECT 'modifications_year_end_before_start', count(*)
FROM public.modifications
WHERE year_start IS NOT NULL AND year_end IS NOT NULL AND year_end < year_start
UNION ALL
SELECT 'generations_open_year_end', count(*)
FROM public.generations
WHERE year_start IS NOT NULL AND year_end IS NULL
UNION ALL
SELECT 'generations_missing_year_start', count(*)
FROM public.generations
WHERE year_start IS NULL;

-- A10. model_photos rights and verified flag. Do not select image bytes.
SELECT count(*) AS rows,
       count(*) FILTER (WHERE verified IS TRUE) AS verified_true,
       count(*) FILTER (WHERE verified IS NOT TRUE) AS not_verified
FROM public.model_photos;

-- A11. Run only if A2 shows public.model_photos.image. Do not select the bytea value.
-- SELECT count(*) FILTER (WHERE image IS NOT NULL) AS with_image_bytes,
--        count(*) FILTER (WHERE verified IS TRUE AND image IS NOT NULL) AS verified_with_bytes
-- FROM public.model_photos;

-- A12. Run only if A2 shows public.generations.photo_urls.
-- SELECT count(*) FILTER (
--            WHERE jsonb_typeof(photo_urls) = 'array' AND jsonb_array_length(photo_urls) > 0
--        ) AS generations_with_photo_urls
-- FROM public.generations;

-- B1. platform.project_limits, including whether per_day exists (see A2 as well).
SELECT column_name, data_type, column_default, is_nullable
FROM information_schema.columns
WHERE table_schema = 'platform' AND table_name = 'project_limits'
ORDER BY ordinal_position;

SELECT count(*) AS projects,
       count(*) FILTER (WHERE per_day IS NOT NULL) AS with_per_day,
       min(per_day) AS per_day_min,
       max(per_day) AS per_day_max
FROM platform.project_limits;

-- B2. Counts only. Do not select password_hash, secret_hash, or tokens.
SELECT 'platform.users' AS object_name, count(*) AS rows FROM platform.users
UNION ALL SELECT 'platform.projects', count(*) FROM platform.projects
UNION ALL SELECT 'platform.api_keys', count(*) FROM platform.api_keys
UNION ALL SELECT 'platform.api_keys_active', count(*) FROM platform.api_keys WHERE revoked_at IS NULL
UNION ALL SELECT 'platform.widgets', count(*) FROM platform.widgets;

-- C1. Laravel migration log. This is the read-only stand-in for migrate:status.
-- Also paste `php artisan migrate:status` from the app directory. Do not paste .env.
SELECT id, migration, batch
FROM public.migrations
ORDER BY id;
