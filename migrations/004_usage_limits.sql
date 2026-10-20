CREATE TABLE IF NOT EXISTS platform.project_limits (
    project_id bigint PRIMARY KEY REFERENCES platform.projects (id),
    per_minute integer NOT NULL CHECK (per_minute > 0),
    monthly_quota integer NOT NULL CHECK (monthly_quota > 0)
);

INSERT INTO platform.project_limits (project_id, per_minute, monthly_quota)
SELECT id, 60, 10000
FROM platform.projects p
WHERE NOT EXISTS (
    SELECT 1 FROM platform.project_limits l WHERE l.project_id = p.id
);

CREATE TABLE IF NOT EXISTS platform.monthly_usage (
    project_id bigint NOT NULL REFERENCES platform.projects (id),
    period date NOT NULL,
    used_count integer NOT NULL DEFAULT 0 CHECK (used_count >= 0),
    PRIMARY KEY (project_id, period)
);

CREATE TABLE IF NOT EXISTS platform.daily_usage (
    project_id bigint NOT NULL REFERENCES platform.projects (id),
    day date NOT NULL,
    route text NOT NULL,
    used_count integer NOT NULL DEFAULT 0 CHECK (used_count >= 0),
    PRIMARY KEY (project_id, day, route)
);

CREATE TABLE IF NOT EXISTS platform.rate_windows (
    scope text NOT NULL CHECK (scope IN ('project', 'ip')),
    subject text NOT NULL,
    window_start timestamptz NOT NULL,
    hit_count integer NOT NULL DEFAULT 0 CHECK (hit_count >= 0),
    PRIMARY KEY (scope, subject, window_start)
);
