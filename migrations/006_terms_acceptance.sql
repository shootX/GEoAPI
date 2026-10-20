ALTER TABLE platform.users
    ADD COLUMN IF NOT EXISTS terms_version text,
    ADD COLUMN IF NOT EXISTS terms_accepted_at timestamptz;
