import type {
  Body,
  Brand,
  Color,
  Coverage,
  Engine,
  Fuel,
  Generation,
  GenerationModification,
  Modification,
  ModificationEquipment,
  ModificationSearch,
  ModificationSpecs,
  Model,
  Year,
} from "./generated.js";

export type {
  Body,
  Brand,
  Color,
  Coverage,
  Engine,
  EquipmentItem,
  Fuel,
  Generation,
  GenerationModification,
  Modification,
  ModificationEquipment,
  ModificationSearch,
  ModificationSpecs,
  Model,
  Year,
} from "./generated.js";

export class GeoAPIError extends Error {
  readonly status: number;
  readonly code: string | null;
  readonly requestId: null;

  constructor(status: number, code: string | null, message: string) {
    super(message);
    this.name = "GeoAPIError";
    this.status = status;
    this.code = code;
    this.requestId = null;
  }
}

export interface GeoAPIOptions {
  apiKey: string;
  baseUrl?: string;
  timeout?: number;
  fetch?: typeof fetch;
  retries?: number;
}

export interface CallOptions {
  signal?: AbortSignal;
}

export interface ListOptions extends CallOptions {
  page?: number;
  limit?: number;
  per_page?: number;
  sort?: string;
  direction?: "asc" | "desc";
  q?: string;
  modified_after?: string;
}

export interface ModelQuery extends ListOptions {
  body?: string;
}

export interface GenerationQuery extends ListOptions {
  year_from?: number;
  year_to?: number;
  body?: string;
}

export interface ModificationFilterQuery extends ListOptions {
  year_from?: number;
  year_to?: number;
  body?: string;
  fuel?: string;
  drive?: string;
  transmission?: string;
}

export interface ModificationQuery extends ModificationFilterQuery {
  brand_id?: string;
  model_id?: string;
}

export interface EngineQuery extends ListOptions {
  fuel?: string;
  brand_id?: string;
  model_id?: string;
  volume_from?: number;
  volume_to?: number;
}

export interface BodyQuery extends CallOptions {
  page?: number;
  limit?: number;
  per_page?: number;
  sort?: string;
  direction?: "asc" | "desc";
  q?: string;
}

export interface YearQuery extends CallOptions {
  page?: number;
  limit?: number;
  per_page?: number;
  sort?: string;
  direction?: "asc" | "desc";
}

export interface ColorQuery extends ListOptions {
  brand_id?: string;
  model_id?: string;
}

export interface ListMeta {
  page: number;
  per_page: number;
  total: number;
}

export interface ListLinks {
  count: number;
  total: number;
  pages: number;
  next: string | null;
  prev: string | null;
  first: string | null;
  last: string | null;
}

export interface ListResult<T> {
  data: T[];
  meta: ListMeta;
  collection: ListLinks;
}

export interface DataResult<T> {
  data: T;
}

const RETRYABLE = new Set([429, 503]);
const MAX_WAIT_MS = 2000;

interface Transport {
  apiKey: string;
  baseUrl: string;
  timeout: number;
  fetch: typeof fetch;
  retries: number;
}

function retryWaitMs(header: string | null): number {
  if (header == null || header.trim() === "") {
    return 0;
  }
  const seconds = Number(header);
  if (!Number.isFinite(seconds) || seconds <= 0) {
    return 0;
  }
  return Math.min(MAX_WAIT_MS, Math.round(seconds * 1000));
}

function sleep(ms: number, signal?: AbortSignal): Promise<void> {
  if (signal?.aborted) {
    return Promise.reject(signal.reason);
  }
  if (ms <= 0) {
    return Promise.resolve();
  }
  return new Promise((resolve, reject) => {
    const onAbort = () => {
      clearTimeout(timer);
      reject(signal?.reason);
    };
    const timer = setTimeout(() => {
      signal?.removeEventListener("abort", onAbort);
      resolve();
    }, ms);
    signal?.addEventListener("abort", onAbort, { once: true });
  });
}

function scrub(message: string, apiKey: string): string {
  if (apiKey.length > 0 && message.includes(apiKey)) {
    return message.split(apiKey).join("[redacted]");
  }
  return message;
}

function queryString(query: object | undefined): string {
  if (!query) {
    return "";
  }
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (key === "signal" || value === undefined || value === null) {
      continue;
    }
    params.append(key, String(value));
  }
  const text = params.toString();
  return text === "" ? "" : `?${text}`;
}

function callerSignal(query: object | undefined): AbortSignal | undefined {
  if (query && "signal" in query && query.signal instanceof AbortSignal) {
    return query.signal;
  }
  return undefined;
}

async function request<T>(transport: Transport, path: string, query?: object): Promise<T> {
  const signal = callerSignal(query);
  if (signal?.aborted) {
    throw signal.reason;
  }
  const url = `${transport.baseUrl}${path}${queryString(query)}`;
  let attempt = 0;
  while (true) {
    const timeout = new AbortController();
    const timer = transport.timeout > 0 ? setTimeout(() => timeout.abort(), transport.timeout) : undefined;
    const timeoutSignal = timer === undefined ? undefined : timeout.signal;
    const signals = [signal, timeoutSignal].filter((item): item is AbortSignal => item !== undefined);
    const combined = signals.length === 0 ? undefined : signals.length === 1 ? signals[0] : AbortSignal.any(signals);
    let response: Response;
    try {
      response = await transport.fetch(url, {
        method: "GET",
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${transport.apiKey}`,
        },
        signal: combined,
      });
    } finally {
      if (timer !== undefined) {
        clearTimeout(timer);
      }
    }
    if (response.ok) {
      return (await response.json()) as T;
    }
    const retryAfter = response.headers.get("Retry-After");
    const text = await response.text();
    if (RETRYABLE.has(response.status) && attempt < transport.retries) {
      attempt += 1;
      await sleep(retryWaitMs(retryAfter), signal);
      continue;
    }
    let code: string | null = null;
    let message = `HTTP ${response.status}`;
    try {
      const body = JSON.parse(text) as { error?: { code?: unknown; message?: unknown } };
      if (typeof body.error?.code === "string") {
        code = body.error.code;
      }
      if (typeof body.error?.message === "string" && body.error.message !== "") {
        message = body.error.message;
      }
    } catch {
      message = `HTTP ${response.status}`;
    }
    throw new GeoAPIError(response.status, code, scrub(message, transport.apiKey));
  }
}

function segment(value: string): string {
  return encodeURIComponent(value);
}

export class GeoAPI {
  readonly brands: { list: (query?: ListOptions) => Promise<ListResult<Brand>> };
  readonly models: { byBrand: (slug: string, query?: ModelQuery) => Promise<ListResult<Model>> };
  readonly years: { byModel: (id: string, query?: YearQuery) => Promise<ListResult<Year>> };
  readonly generations: {
    byModel: (id: string, query?: GenerationQuery) => Promise<ListResult<Generation>>;
    modifications: (id: string, query?: ModificationFilterQuery) => Promise<ListResult<GenerationModification>>;
  };
  readonly modifications: {
    list: (query?: ModificationQuery) => Promise<ListResult<ModificationSearch>>;
    get: (id: string, query?: CallOptions) => Promise<DataResult<Modification>>;
    specs: (id: string, query?: CallOptions) => Promise<DataResult<ModificationSpecs>>;
    equipment: (id: string, query?: CallOptions) => Promise<DataResult<ModificationEquipment>>;
  };
  readonly engines: { list: (query?: EngineQuery) => Promise<ListResult<Engine>> };
  readonly bodies: { list: (query?: BodyQuery) => Promise<ListResult<Body>> };
  readonly fuel: { list: (query?: ModificationQuery) => Promise<ListResult<Fuel>> };
  readonly colors: { list: (query?: ColorQuery) => Promise<ListResult<Color>> };
  readonly coverage: { get: (query?: CallOptions) => Promise<Coverage> };

  constructor(options: GeoAPIOptions) {
    const browser = (globalThis as { window?: unknown }).window !== undefined;
    if (browser && options.apiKey.startsWith("geoapi_sk_")) {
      throw new GeoAPIError(0, null, "A secret key cannot be used in the browser.");
    }
    const transport: Transport = {
      apiKey: options.apiKey,
      baseUrl: (options.baseUrl ?? "https://geoapi.ge").replace(/\/+$/, ""),
      timeout: options.timeout ?? 30_000,
      fetch: options.fetch ?? globalThis.fetch.bind(globalThis),
      retries: options.retries ?? 2,
    };
    const send = <T>(path: string, query?: object) => request<T>(transport, path, query);
    this.brands = { list: (query) => send("/api/v1/brands", query) };
    this.models = { byBrand: (slug, query) => send(`/api/v1/brands/${segment(slug)}/models`, query) };
    this.years = { byModel: (id, query) => send(`/api/v1/models/${segment(id)}/years`, query) };
    this.generations = {
      byModel: (id, query) => send(`/api/v1/models/${segment(id)}/generations`, query),
      modifications: (id, query) => send(`/api/v1/generations/${segment(id)}/modifications`, query),
    };
    this.modifications = {
      list: (query) => send("/api/v1/modifications", query),
      get: (id, query) => send(`/api/v1/modifications/${segment(id)}`, query),
      specs: (id, query) => send(`/api/v1/modifications/${segment(id)}/specs`, query),
      equipment: (id, query) => send(`/api/v1/modifications/${segment(id)}/equipment`, query),
    };
    this.engines = { list: (query) => send("/api/v1/engines", query) };
    this.bodies = { list: (query) => send("/api/v1/bodies", query) };
    this.fuel = { list: (query) => send("/api/v1/fuel", query) };
    this.colors = { list: (query) => send("/api/v1/colors", query) };
    this.coverage = { get: (query) => send("/api/v1/coverage", query) };
  }
}
