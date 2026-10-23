<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/**
 * Indexes and updated_at for the public catalog, plus the Free daily quota.
 * Runs as the table owner. The reader role is not used here.
 * Tests skip it: the catalog connection in PHPUnit is SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $this->catalog();
        $this->platform();
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $catalog = $this->catalogPdo();
        foreach ([
            'modifications_year_start_idx',
            'modifications_year_end_idx',
            'modifications_body_type_idx',
            'modifications_drive_type_idx',
            'modifications_transmission_idx',
            'modifications_engine_volume_idx',
            'modifications_updated_at_idx',
            'generations_body_type_idx',
            'generations_updated_at_idx',
            'generations_year_idx',
            'models_body_type_idx',
            'models_updated_at_idx',
            'brands_name_idx',
            'brands_updated_at_idx',
            'model_photos_color_idx',
            'model_photos_updated_at_idx',
        ] as $index) {
            $catalog->exec('DROP INDEX IF EXISTS public.'.$index);
        }

        $platform = $this->platformPdo();
        $platform->exec('ALTER TABLE platform.project_limits DROP COLUMN IF EXISTS per_day');
    }

    private function catalog(): void
    {
        $pdo = $this->catalogPdo();
        $pdo->exec("SET statement_timeout = '120s'");
        $pdo->exec(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.geoapi_touch_updated_at()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$
        SQL);
        foreach (['brands', 'models', 'generations', 'modifications', 'model_photos'] as $table) {
            $pdo->exec('ALTER TABLE public.'.$table.' ADD COLUMN IF NOT EXISTS updated_at timestamp with time zone');
            $pdo->exec('ALTER TABLE public.'.$table.' ALTER COLUMN updated_at SET DEFAULT CURRENT_TIMESTAMP');
            $pdo->exec('UPDATE public.'.$table.' SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL');
            $pdo->exec('DROP TRIGGER IF EXISTS geoapi_'.$table.'_updated_at ON public.'.$table);
            $pdo->exec('CREATE TRIGGER geoapi_'.$table.'_updated_at BEFORE UPDATE ON public.'.$table.' FOR EACH ROW EXECUTE PROCEDURE public.geoapi_touch_updated_at()');
        }
        $pdo->exec(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.geoapi_touch_modification()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                UPDATE public.modifications SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.modification_id;
                RETURN NEW;
            END;
            $$
        SQL);
        $pdo->exec('DROP TRIGGER IF EXISTS geoapi_specs_touch_modification ON public.modification_specs');
        $pdo->exec('CREATE TRIGGER geoapi_specs_touch_modification AFTER INSERT OR UPDATE ON public.modification_specs FOR EACH ROW EXECUTE PROCEDURE public.geoapi_touch_modification()');
        foreach ([
            'CREATE INDEX IF NOT EXISTS modifications_year_start_idx ON public.modifications (year_start)',
            'CREATE INDEX IF NOT EXISTS modifications_year_end_idx ON public.modifications (year_end)',
            'CREATE INDEX IF NOT EXISTS modifications_body_type_idx ON public.modifications (body_type)',
            'CREATE INDEX IF NOT EXISTS modifications_drive_type_idx ON public.modifications (drive_type)',
            'CREATE INDEX IF NOT EXISTS modifications_transmission_idx ON public.modifications (transmission)',
            'CREATE INDEX IF NOT EXISTS modifications_engine_volume_idx ON public.modifications (engine_volume_cc)',
            'CREATE INDEX IF NOT EXISTS modifications_updated_at_idx ON public.modifications (updated_at)',
            'CREATE INDEX IF NOT EXISTS generations_body_type_idx ON public.generations (body_type)',
            'CREATE INDEX IF NOT EXISTS generations_updated_at_idx ON public.generations (updated_at)',
            'CREATE INDEX IF NOT EXISTS generations_year_idx ON public.generations (model_id, year_start, year_end)',
            'CREATE INDEX IF NOT EXISTS models_body_type_idx ON public.models (body_type)',
            'CREATE INDEX IF NOT EXISTS models_updated_at_idx ON public.models (updated_at)',
            'CREATE INDEX IF NOT EXISTS brands_name_idx ON public.brands (name)',
            'CREATE INDEX IF NOT EXISTS brands_updated_at_idx ON public.brands (updated_at)',
            'CREATE INDEX IF NOT EXISTS model_photos_color_idx ON public.model_photos (color)',
            'CREATE INDEX IF NOT EXISTS model_photos_updated_at_idx ON public.model_photos (updated_at)',
        ] as $sql) {
            $pdo->exec($sql);
        }
    }

    private function platform(): void
    {
        $pdo = $this->platformPdo();
        $pdo->exec('ALTER TABLE platform.project_limits ADD COLUMN IF NOT EXISTS per_day integer NOT NULL DEFAULT 2000');
    }

    private function catalogPdo(): PDO
    {
        $host = $this->env('GEOAPI_CATALOG_ADMIN_HOST', 'GEOAPI_CATALOG_HOST');
        $database = $this->env('GEOAPI_CATALOG_ADMIN_DATABASE', 'GEOAPI_CATALOG_DATABASE');
        $user = $this->env('GEOAPI_CATALOG_ADMIN_USER');
        $password = $this->env('GEOAPI_CATALOG_ADMIN_PASSWORD');
        $port = $this->env('GEOAPI_CATALOG_ADMIN_PORT', 'GEOAPI_CATALOG_PORT');
        if ($port === '') {
            $port = '5432';
        }
        if ($host === '' || $database === '' || $user === '') {
            throw new RuntimeException('Catalog migration needs GEOAPI_CATALOG_ADMIN_USER, the table owner. Do not use geoapi_catalog_reader.');
        }

        return $this->pdo($host, $port, $database, $user, $password);
    }

    private function platformPdo(): PDO
    {
        $host = $this->env('GEOAPI_PLATFORM_HOST');
        $database = $this->env('GEOAPI_PLATFORM_DATABASE');
        $user = $this->env('GEOAPI_PLATFORM_USER');
        $password = $this->env('GEOAPI_PLATFORM_PASSWORD');
        $port = $this->env('GEOAPI_PLATFORM_PORT');
        if ($port === '') {
            $port = '5432';
        }
        if ($host === '' || $database === '' || $user === '') {
            throw new RuntimeException('Platform migration needs GEOAPI_PLATFORM_HOST, GEOAPI_PLATFORM_DATABASE, and GEOAPI_PLATFORM_USER.');
        }

        return $this->pdo($host, $port, $database, $user, $password);
    }

    private function pdo(string $host, string $port, string $database, string $user, string $password): PDO
    {
        return new PDO(
            sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, (int) $port, $database),
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    private function env(string $primary, string $fallback = ''): string
    {
        $value = env($primary);
        if (! is_string($value) || trim($value) === '') {
            $value = $fallback === '' ? '' : env($fallback);
        }

        return is_string($value) ? trim($value) : '';
    }
};
