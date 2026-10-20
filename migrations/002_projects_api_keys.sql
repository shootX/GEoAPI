CREATE TABLE IF NOT EXISTS platform.projects (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id bigint NOT NULL REFERENCES platform.users (id),
    name text NOT NULL,
    website_url text,
    status text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'disabled')),
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS projects_user_id_idx ON platform.projects (user_id);

CREATE TABLE IF NOT EXISTS platform.api_keys (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    project_id bigint NOT NULL REFERENCES platform.projects (id),
    name text NOT NULL,
    key_selector text NOT NULL,
    secret_hash text NOT NULL,
    scope text NOT NULL DEFAULT 'catalog:read' CHECK (scope = 'catalog:read'),
    created_at timestamptz NOT NULL DEFAULT now(),
    expires_at timestamptz,
    revoked_at timestamptz,
    last_used_at timestamptz
);

CREATE UNIQUE INDEX IF NOT EXISTS api_keys_selector_uidx ON platform.api_keys (key_selector);
CREATE INDEX IF NOT EXISTS api_keys_project_id_idx ON platform.api_keys (project_id);
