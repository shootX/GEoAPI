-- RECONSTRUCTED platform schema for local development and tests. NOT the production DDL.
--
-- The production schema is not in this repository. This file was rebuilt on 2026-10-22 from the
-- queries in legacy/src and app/, with columns added until the app ran end to end on a local
-- stack (QA retest). Column types, defaults, indexes and constraints may differ from production;
-- do not use it to change or recreate a production database.
--
-- Load into an empty database, then run `php artisan migrate` against the same database.
--
-- PostgreSQL database dump
--


-- Dumped from database version 18.6 (Debian 18.6-1.pgdg13+2)
-- Dumped by pg_dump version 18.6 (Debian 18.6-1.pgdg13+2)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: platform; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA platform;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: api_keys; Type: TABLE; Schema: platform; Owner: -
--

CREATE TABLE platform.api_keys (
    id integer NOT NULL,
    project_id integer NOT NULL,
    name text,
    key_selector text NOT NULL,
    secret_hash text NOT NULL,
    scope text DEFAULT 'catalog:read'::text NOT NULL,
    created_at timestamp with time zone DEFAULT now(),
    expires_at timestamp with time zone,
    revoked_at timestamp with time zone,
    last_used_at timestamp with time zone
);


--
-- Name: api_keys_id_seq; Type: SEQUENCE; Schema: platform; Owner: -
--

CREATE SEQUENCE platform.api_keys_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: api_keys_id_seq; Type: SEQUENCE OWNED BY; Schema: platform; Owner: -
--

ALTER SEQUENCE platform.api_keys_id_seq OWNED BY platform.api_keys.id;


--
-- Name: daily_usage; Type: TABLE; Schema: platform; Owner: -
--

CREATE TABLE platform.daily_usage (
    project_id integer NOT NULL,
    day date NOT NULL,
    route text NOT NULL,
    used_count integer NOT NULL
);


--
-- Name: email_tokens; Type: TABLE; Schema: platform; Owner: -
--

CREATE TABLE platform.email_tokens (
    id integer NOT NULL,
    user_id integer,
    purpose text,
    token_hash text,
    expires_at timestamp with time zone,
    used_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT now(),
    email_snapshot text,
    revoked_at timestamp with time zone
);


--
-- Name: email_tokens_id_seq; Type: SEQUENCE; Schema: platform; Owner: -
--

CREATE SEQUENCE platform.email_tokens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: email_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: platform; Owner: -
--

ALTER SEQUENCE platform.email_tokens_id_seq OWNED BY platform.email_tokens.id;


--
-- Name: login_attempts; Type: TABLE; Schema: platform; Owner: -
--

CREATE TABLE platform.login_attempts (
    id integer NOT NULL,
    email text,
    ip text,
    created_at timestamp with time zone DEFAULT now()
);


--
-- Name: login_attempts_id_seq; Type: SEQUENCE; Schema: platform; Owner: -
--

CREATE SEQUENCE platform.login_attempts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: login_attempts_id_seq; Type: SEQUENCE OWNED BY; Schema: platform; Owner: -
--

ALTER SEQUENCE platform.login_attempts_id_seq OWNED BY platform.login_attempts.id;


--
-- Name: mail_limits; Type: TABLE; Schema: platform; Owner: -
--

CREATE TABLE platform.mail_limits (
    id integer NOT NULL,
    subject text,
    kind text,
    purpose text,
    created_at timestamp with time zone DEFAULT now()
);


--
-- Name: mail_limits_id_seq; Type: SEQUENCE; Schema: platform; Owner: -
--

CREATE SEQUENCE platform.mail_limits_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mail_limits_id_seq; Type: SEQUENCE OWNED BY; Schema: platform; Owner: -
--

ALTER SEQUENCE platform.mail_limits_id_seq OWNED BY platform.mail_limits.id;


--
-- Name: monthly_usage; Type: TABLE; Schema: platform; Owner: -
--

CREATE TABLE platform.monthly_usage (
    project_id integer NOT NULL,
    period text NOT NULL,
    used_count integer NOT NULL
);


--
-- Name: project_limits; Type: TABLE; Schema: platform; Owner: -
--

CREATE TABLE platform.project_limits (
    project_id integer NOT NULL,
    per_minute integer NOT NULL,
    monthly_quota integer NOT NULL,
    per_day integer NOT NULL,
    plan_code text,
    plan_ends_at timestamp with time zone,
    unlimited_month boolean DEFAULT false NOT NULL
);


--
-- Name: projects; Type: TABLE; Schema: platform; Owner: -
--

CREATE TABLE platform.projects (
    id integer NOT NULL,
    user_id integer NOT NULL,
    name text,
    website_url text,
    status text DEFAULT 'active'::text NOT NULL,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    website text
);


--
-- Name: projects_id_seq; Type: SEQUENCE; Schema: platform; Owner: -
--

CREATE SEQUENCE platform.projects_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: projects_id_seq; Type: SEQUENCE OWNED BY; Schema: platform; Owner: -
--

ALTER SEQUENCE platform.projects_id_seq OWNED BY platform.projects.id;


--
-- Name: rate_windows; Type: TABLE; Schema: platform; Owner: -
--

CREATE TABLE platform.rate_windows (
    scope text NOT NULL,
    subject text NOT NULL,
    window_start timestamp with time zone NOT NULL,
    hit_count integer NOT NULL,
    CONSTRAINT rate_windows_scope_check CHECK ((scope = ANY (ARRAY['project'::text, 'ip'::text, 'workspace'::text])))
);


--
-- Name: users; Type: TABLE; Schema: platform; Owner: -
--

CREATE TABLE platform.users (
    id integer NOT NULL,
    first_name text,
    last_name text,
    email text,
    password_hash text,
    status text DEFAULT 'active'::text NOT NULL,
    email_verified_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    grandfathered boolean DEFAULT false NOT NULL,
    auth_version integer DEFAULT 1 NOT NULL,
    terms_version text,
    terms_accepted_at timestamp with time zone,
    role text DEFAULT 'user'::text NOT NULL,
    last_login_at timestamp with time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: platform; Owner: -
--

CREATE SEQUENCE platform.users_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: platform; Owner: -
--

ALTER SEQUENCE platform.users_id_seq OWNED BY platform.users.id;


--
-- Name: widgets; Type: TABLE; Schema: platform; Owner: -
--

CREATE TABLE platform.widgets (
    project_id integer NOT NULL,
    enabled integer DEFAULT 1 NOT NULL,
    public_key text NOT NULL,
    allowed_origins text DEFAULT '[]'::text NOT NULL,
    updated_at timestamp with time zone
);


--
-- Name: api_keys id; Type: DEFAULT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.api_keys ALTER COLUMN id SET DEFAULT nextval('platform.api_keys_id_seq'::regclass);


--
-- Name: email_tokens id; Type: DEFAULT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.email_tokens ALTER COLUMN id SET DEFAULT nextval('platform.email_tokens_id_seq'::regclass);


--
-- Name: login_attempts id; Type: DEFAULT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.login_attempts ALTER COLUMN id SET DEFAULT nextval('platform.login_attempts_id_seq'::regclass);


--
-- Name: mail_limits id; Type: DEFAULT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.mail_limits ALTER COLUMN id SET DEFAULT nextval('platform.mail_limits_id_seq'::regclass);


--
-- Name: projects id; Type: DEFAULT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.projects ALTER COLUMN id SET DEFAULT nextval('platform.projects_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.users ALTER COLUMN id SET DEFAULT nextval('platform.users_id_seq'::regclass);


--
-- Name: api_keys api_keys_pkey; Type: CONSTRAINT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.api_keys
    ADD CONSTRAINT api_keys_pkey PRIMARY KEY (id);


--
-- Name: daily_usage daily_usage_pkey; Type: CONSTRAINT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.daily_usage
    ADD CONSTRAINT daily_usage_pkey PRIMARY KEY (project_id, day, route);


--
-- Name: email_tokens email_tokens_pkey; Type: CONSTRAINT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.email_tokens
    ADD CONSTRAINT email_tokens_pkey PRIMARY KEY (id);


--
-- Name: login_attempts login_attempts_pkey; Type: CONSTRAINT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.login_attempts
    ADD CONSTRAINT login_attempts_pkey PRIMARY KEY (id);


--
-- Name: mail_limits mail_limits_pkey; Type: CONSTRAINT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.mail_limits
    ADD CONSTRAINT mail_limits_pkey PRIMARY KEY (id);


--
-- Name: monthly_usage monthly_usage_pkey; Type: CONSTRAINT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.monthly_usage
    ADD CONSTRAINT monthly_usage_pkey PRIMARY KEY (project_id, period);


--
-- Name: project_limits project_limits_pkey; Type: CONSTRAINT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.project_limits
    ADD CONSTRAINT project_limits_pkey PRIMARY KEY (project_id);


--
-- Name: projects projects_pkey; Type: CONSTRAINT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.projects
    ADD CONSTRAINT projects_pkey PRIMARY KEY (id);


--
-- Name: rate_windows rate_windows_pkey; Type: CONSTRAINT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.rate_windows
    ADD CONSTRAINT rate_windows_pkey PRIMARY KEY (scope, subject, window_start);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: widgets widgets_pkey; Type: CONSTRAINT; Schema: platform; Owner: -
--

ALTER TABLE ONLY platform.widgets
    ADD CONSTRAINT widgets_pkey PRIMARY KEY (project_id);


--
-- PostgreSQL database dump complete
--


