-- Existing rows receive user. This statement does not grant admin.
ALTER TABLE platform.users
    ADD COLUMN IF NOT EXISTS role text NOT NULL DEFAULT 'user' CONSTRAINT users_role_check CHECK (role IN ('user', 'admin'));
