CREATE TABLE IF NOT EXISTS platform.widgets (
    project_id bigint PRIMARY KEY REFERENCES platform.projects (id),
    enabled boolean NOT NULL DEFAULT false,
    public_key text NOT NULL,
    allowed_origins jsonb NOT NULL DEFAULT '[]'::jsonb,
    updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS widgets_public_key_uidx ON platform.widgets (public_key);
