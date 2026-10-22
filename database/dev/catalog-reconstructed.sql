-- RECONSTRUCTED catalog (carbase) schema for local development and tests. NOT the production DDL.
--
-- The production schema is not in this repository. This file was rebuilt on 2026-10-22 from the
-- queries in legacy/src and app/, with columns added until the app ran end to end on a local
-- stack (QA retest). Column types, defaults, indexes and constraints may differ from production;
-- do not use it to change or recreate a production database.
--
-- Load into the database named by CARAPI_CATALOG_DATABASE.
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
-- Name: carapi_touch_modification(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.carapi_touch_modification() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        UPDATE public.modifications SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.modification_id;
        RETURN NEW;
    END;
    $$;


--
-- Name: carapi_touch_updated_at(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.carapi_touch_updated_at() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        NEW.updated_at = CURRENT_TIMESTAMP;
        RETURN NEW;
    END;
    $$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: brands; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.brands (
    id integer NOT NULL,
    slug text NOT NULL,
    name text NOT NULL,
    synonyms jsonb,
    is_lcv_or_commercial integer,
    logo_url_light text,
    logo_url_dark text,
    updated_at text DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: generations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.generations (
    id integer NOT NULL,
    model_id integer NOT NULL,
    slug text,
    name text,
    chassis_code text,
    body_type text,
    year_start integer,
    year_end integer,
    steering_wheel text,
    updated_at text DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: model_markets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_markets (
    model_id integer NOT NULL,
    market_slug text NOT NULL
);


--
-- Name: model_photos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_photos (
    model_id integer NOT NULL,
    color text,
    verified integer,
    prompt text,
    updated_at text DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: models; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.models (
    id integer NOT NULL,
    brand_id integer NOT NULL,
    slug text NOT NULL,
    name text NOT NULL,
    body_type text,
    updated_at text DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: modification_equipment; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.modification_equipment (
    id integer NOT NULL,
    modification_id integer NOT NULL,
    category text,
    name text,
    status text,
    value text
);


--
-- Name: modification_specs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.modification_specs (
    modification_id integer NOT NULL,
    specs jsonb
);


--
-- Name: modifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.modifications (
    id integer NOT NULL,
    generation_id integer,
    model_id integer NOT NULL,
    name text NOT NULL,
    year_start integer,
    year_end integer,
    body_type text,
    drive_type text,
    transmission text,
    engine_volume_cc integer,
    updated_at text DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: photos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.photos (
    id integer NOT NULL
);


--
-- Name: brands brands_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.brands
    ADD CONSTRAINT brands_pkey PRIMARY KEY (id);


--
-- Name: generations generations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.generations
    ADD CONSTRAINT generations_pkey PRIMARY KEY (id);


--
-- Name: model_photos model_photos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_photos
    ADD CONSTRAINT model_photos_pkey PRIMARY KEY (model_id);


--
-- Name: models models_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.models
    ADD CONSTRAINT models_pkey PRIMARY KEY (id);


--
-- Name: modification_equipment modification_equipment_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modification_equipment
    ADD CONSTRAINT modification_equipment_pkey PRIMARY KEY (id);


--
-- Name: modification_specs modification_specs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modification_specs
    ADD CONSTRAINT modification_specs_pkey PRIMARY KEY (modification_id);


--
-- Name: modifications modifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modifications
    ADD CONSTRAINT modifications_pkey PRIMARY KEY (id);


--
-- Name: photos photos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photos
    ADD CONSTRAINT photos_pkey PRIMARY KEY (id);


--
-- Name: brands_name_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX brands_name_idx ON public.brands USING btree (name);


--
-- Name: brands_updated_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX brands_updated_at_idx ON public.brands USING btree (updated_at);


--
-- Name: generations_body_type_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX generations_body_type_idx ON public.generations USING btree (body_type);


--
-- Name: generations_updated_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX generations_updated_at_idx ON public.generations USING btree (updated_at);


--
-- Name: generations_year_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX generations_year_idx ON public.generations USING btree (model_id, year_start, year_end);


--
-- Name: model_photos_color_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_photos_color_idx ON public.model_photos USING btree (color);


--
-- Name: model_photos_updated_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_photos_updated_at_idx ON public.model_photos USING btree (updated_at);


--
-- Name: models_body_type_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX models_body_type_idx ON public.models USING btree (body_type);


--
-- Name: models_updated_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX models_updated_at_idx ON public.models USING btree (updated_at);


--
-- Name: modifications_body_type_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX modifications_body_type_idx ON public.modifications USING btree (body_type);


--
-- Name: modifications_drive_type_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX modifications_drive_type_idx ON public.modifications USING btree (drive_type);


--
-- Name: modifications_engine_volume_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX modifications_engine_volume_idx ON public.modifications USING btree (engine_volume_cc);


--
-- Name: modifications_transmission_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX modifications_transmission_idx ON public.modifications USING btree (transmission);


--
-- Name: modifications_updated_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX modifications_updated_at_idx ON public.modifications USING btree (updated_at);


--
-- Name: modifications_year_end_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX modifications_year_end_idx ON public.modifications USING btree (year_end);


--
-- Name: modifications_year_start_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX modifications_year_start_idx ON public.modifications USING btree (year_start);


--
-- Name: brands carapi_brands_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER carapi_brands_updated_at BEFORE UPDATE ON public.brands FOR EACH ROW EXECUTE FUNCTION public.carapi_touch_updated_at();


--
-- Name: generations carapi_generations_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER carapi_generations_updated_at BEFORE UPDATE ON public.generations FOR EACH ROW EXECUTE FUNCTION public.carapi_touch_updated_at();


--
-- Name: model_photos carapi_model_photos_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER carapi_model_photos_updated_at BEFORE UPDATE ON public.model_photos FOR EACH ROW EXECUTE FUNCTION public.carapi_touch_updated_at();


--
-- Name: models carapi_models_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER carapi_models_updated_at BEFORE UPDATE ON public.models FOR EACH ROW EXECUTE FUNCTION public.carapi_touch_updated_at();


--
-- Name: modifications carapi_modifications_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER carapi_modifications_updated_at BEFORE UPDATE ON public.modifications FOR EACH ROW EXECUTE FUNCTION public.carapi_touch_updated_at();


--
-- Name: modification_specs carapi_specs_touch_modification; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER carapi_specs_touch_modification AFTER INSERT OR UPDATE ON public.modification_specs FOR EACH ROW EXECUTE FUNCTION public.carapi_touch_modification();


--
-- PostgreSQL database dump complete
--


