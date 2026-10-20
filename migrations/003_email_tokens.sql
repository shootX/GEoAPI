ALTER TABLE platform.users
    ADD COLUMN IF NOT EXISTS auth_version integer NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS platform.email_tokens (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id bigint NOT NULL REFERENCES platform.users (id),
    purpose text NOT NULL CHECK (purpose IN ('verify_email', 'reset_password')),
    token_hash text NOT NULL,
    email_snapshot text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    expires_at timestamptz NOT NULL,
    used_at timestamptz,
    revoked_at timestamptz
);

CREATE UNIQUE INDEX IF NOT EXISTS email_tokens_hash_uidx ON platform.email_tokens (token_hash);
CREATE INDEX IF NOT EXISTS email_tokens_user_purpose_idx ON platform.email_tokens (user_id, purpose);

CREATE TABLE IF NOT EXISTS platform.mail_limits (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    subject text NOT NULL,
    kind text NOT NULL CHECK (kind IN ('email', 'ip')),
    purpose text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS mail_limits_lookup_idx ON platform.mail_limits (kind, subject, purpose, created_at);
