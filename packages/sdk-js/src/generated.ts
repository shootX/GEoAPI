// Generated from packages/sdk-js/openapi.json. Do not edit.

export interface List {
    data?: (Record<string, unknown>)[];
    meta?: { page?: number; per_page?: number; total?: number };
    collection?: { count?: number; total?: number; pages?: number; next?: string | null; prev?: string | null; first?: string | null; last?: string | null };
}

export interface Error {
    error: { code: string; message: string };
}

export interface Brand {
    id: string;
    slug: string;
    name: string;
    synonyms: string[];
    is_lcv_or_commercial: boolean;
    logo_url_light: string | null;
    logo_url_dark: string | null;
    modified: string | null;
}

export interface Model {
    id: string;
    brand_id: string;
    slug: string;
    name: string;
    markets: string[];
    has_generations: boolean;
    generations_count: number;
    modified: string | null;
}

export interface Generation {
    id: string;
    model_id: string;
    slug: string;
    name: string | null;
    year_start: number | null;
    year_end: number | null;
    period_text: string | null;
    body_type: string | null;
    chassis_code: string | null;
    steering_wheel: string | null;
    market: string | null;
    display_label: string;
    modified: string | null;
}

export interface Year {
    id: string;
    year: number;
}

export interface GenerationModification {
    id: string;
    generation_id: string;
    name: string;
    engine_volume_cc: number | null;
    transmission: string | null;
    year_start: number | null;
    year_end: number | null;
    modified: string | null;
}

export interface ModificationSearch {
    id: string;
    name: string;
    brand_id: string;
    model_id: string;
    generation_id: string | null;
    engine_volume_cc: number | null;
    transmission: string | null;
    drive_type: string | null;
    body_type: string | null;
    fuel: string | null;
    year_start: number | null;
    year_end: number | null;
    modified: string | null;
}

export interface Modification {
    id: string;
    name: string;
    engine_volume_cc: number | null;
    transmission: string | null;
    drive_type: string | null;
    body_type: string | null;
    year_start: number | null;
    year_end: number | null;
    brand: { id: string; slug: string; name: string };
    model: { id: string; slug: string; name: string };
    generation: { id: string; name: string | null } | null;
    modified: string | null;
}

export interface ModificationSpecs {
    modification_id: string;
    engine: { volume_cc: number | null; fuel: string | null; power_hp: number | null };
    transmission: { type: string | null; drive: string | null };
    dimensions: Record<string, unknown> | null;
    performance: Record<string, unknown> | null;
    consumption: { city_l: number | null; highway_l: number | null; combined_l: number | null; unit: string };
}

export interface EquipmentItem {
    id: string;
    category: string | null;
    name: string;
    value: string | null;
}

export interface ModificationEquipment {
    modification_id: string;
    standard: EquipmentItem[];
    optional: EquipmentItem[];
    not_available: EquipmentItem[];
    unknown: EquipmentItem[];
}

export interface Engine {
    id: string;
    volume_cc: number | null;
    fuel: string | null;
    power_hp: number | null;
    brand_id: string;
    model_id: string;
    modified: string | null;
}

export interface Body {
    id: string;
    name: string;
    count: number;
    modified: string | null;
}

export interface Fuel {
    id: string;
    fuel: string | null;
    city_l: number | null;
    highway_l: number | null;
    combined_l: number | null;
    brand_id: string;
    model_id: string;
    modified: string | null;
}

export interface Color {
    id: string;
    model_id: string;
    brand_id: string;
    name: string;
    verified: boolean;
    modified: string | null;
}

export interface Coverage {
    brands_count: number;
    models_count: number;
    generations_count: number;
    models_with_generations_count: number;
    models_without_generations_count: number;
    modifications_count: number;
    photos_count: number;
    generations_with_photo_urls_count: number;
    measured_at: string;
}
