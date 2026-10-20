-- Idempotent platform schema. Does not touch public catalog tables.
CREATE SCHEMA IF NOT EXISTS platform;

CREATE TABLE IF NOT EXISTS platform.schema_migrations (
    version text PRIMARY KEY,
    applied_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS platform.users (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    first_name text NOT NULL,
    last_name text NOT NULL,
    email text NOT NULL,
    password_hash text NOT NULL,
    status text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'suspended')),
    email_verified_at timestamptz,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    last_login_at timestamptz
);

CREATE UNIQUE INDEX IF NOT EXISTS users_email_lower_uidx
    ON platform.users (lower(email));

CREATE TABLE IF NOT EXISTS platform.login_attempts (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email text,
    ip text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS login_attempts_email_created_idx
    ON platform.login_attempts (email, created_at);
CREATE INDEX IF NOT EXISTS login_attempts_ip_created_idx
    ON platform.login_attempts (ip, created_at);
