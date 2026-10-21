#!/usr/bin/env node
import { pathToFileURL } from "node:url";
import { parseArgs } from "node:util";

import { GeoAPI, GeoAPIError, type BodyQuery, type ListOptions, type YearQuery } from "@geoapi/sdk";

export const HELP = [
  "geoapi <command> [flags]",
  "",
  "Commands:",
  "  brands list",
  "  models list --brand <slug>",
  "  years list --model-id <id>",
  "  generations list --model-id <id>",
  "  generations modifications --id <id>",
  "  modifications list",
  "  modifications get --id <id>",
  "  modifications specs --id <id>",
  "  modifications equipment --id <id>",
  "  engines list",
  "  bodies list",
  "  fuel list",
  "  colors list",
  "  coverage",
  "",
  "Flags:",
  "  --base-url <url>   default https://geoapi.ge",
  "  --json             one compact JSON document on stdout",
  "  --pretty           indented JSON on stdout",
  "  --limit <n>        page size, 1 to 1000",
  "  --page <n>",
  "  --timeout <ms>     default 30000",
  "  --help",
  "",
  "The key is the GEOAPI_API_KEY environment variable. It is not a flag.",
  "stdout is only the JSON document. Errors and the API code go to stderr.",
  "Exit 0 is success, 2 is invalid input, 3 is auth, 4 is rate limited, and 5 is network or service.",
].join("\n");

const OPTIONS = {
  help: { type: "boolean" },
  json: { type: "boolean" },
  pretty: { type: "boolean" },
  "base-url": { type: "string" },
  timeout: { type: "string" },
  limit: { type: "string" },
  page: { type: "string" },
  brand: { type: "string" },
  "brand-id": { type: "string" },
  "model-id": { type: "string" },
  id: { type: "string" },
  "year-from": { type: "string" },
  "year-to": { type: "string" },
  q: { type: "string" },
  sort: { type: "string" },
  direction: { type: "string" },
  body: { type: "string" },
  fuel: { type: "string" },
  drive: { type: "string" },
  transmission: { type: "string" },
  "modified-after": { type: "string" },
  "volume-from": { type: "string" },
  "volume-to": { type: "string" },
  "api-key": { type: "string" },
  key: { type: "string" },
  token: { type: "string" },
} as const;

const SECRET_FLAGS = ["api-key", "key", "token"] as const;

interface Io {
  stdout: { write(chunk: string): void };
  stderr: { write(chunk: string): void };
  env: NodeJS.ProcessEnv;
  fetch?: typeof fetch;
}

interface Parsed {
  pretty: boolean;
  baseUrl?: string;
  timeout: number;
  limit?: number;
  page?: number;
  q?: string;
  sort?: string;
  direction?: "asc" | "desc";
  modified_after?: string;
  body?: string;
  fuel?: string;
  drive?: string;
  transmission?: string;
  year_from?: number;
  year_to?: number;
  volume_from?: number;
  volume_to?: number;
  brand?: string;
  brand_id?: string;
  model_id?: string;
  id?: string;
}

interface Command {
  words: string[];
  allow: string[];
  required: string[];
  call: (api: GeoAPI, flags: Parsed) => Promise<unknown>;
}

const paging = (flags: Parsed): ListOptions => ({
  limit: flags.limit,
  page: flags.page,
  q: flags.q,
  sort: flags.sort,
  direction: flags.direction,
  modified_after: flags.modified_after,
});

const yearsQuery = (flags: Parsed): YearQuery => ({
  limit: flags.limit,
  page: flags.page,
  sort: flags.sort,
  direction: flags.direction,
});

const bodiesQuery = (flags: Parsed): BodyQuery => ({
  limit: flags.limit,
  page: flags.page,
  q: flags.q,
  sort: flags.sort,
  direction: flags.direction,
});

const COMMANDS: Command[] = [
  { words: ["brands", "list"], allow: ["limit", "page", "q", "sort", "direction", "modified-after"], required: [], call: (api, flags) => api.brands.list(paging(flags)) },
  { words: ["models", "list"], allow: ["brand", "limit", "page", "q", "sort", "direction", "modified-after", "body"], required: ["brand"], call: (api, flags) => api.models.byBrand(flags.brand ?? "", { ...paging(flags), body: flags.body }) },
  { words: ["years", "list"], allow: ["model-id", "limit", "page", "sort", "direction"], required: ["model-id"], call: (api, flags) => api.years.byModel(flags.model_id ?? "", yearsQuery(flags)) },
  { words: ["generations", "list"], allow: ["model-id", "limit", "page", "q", "sort", "direction", "modified-after", "year-from", "year-to", "body"], required: ["model-id"], call: (api, flags) => api.generations.byModel(flags.model_id ?? "", { ...paging(flags), year_from: flags.year_from, year_to: flags.year_to, body: flags.body }) },
  { words: ["generations", "modifications"], allow: ["id", "limit", "page", "q", "sort", "direction", "modified-after", "year-from", "year-to", "body", "fuel", "drive", "transmission"], required: ["id"], call: (api, flags) => api.generations.modifications(flags.id ?? "", { ...paging(flags), year_from: flags.year_from, year_to: flags.year_to, body: flags.body, fuel: flags.fuel, drive: flags.drive, transmission: flags.transmission }) },
  { words: ["modifications", "list"], allow: ["limit", "page", "q", "sort", "direction", "modified-after", "year-from", "year-to", "body", "fuel", "drive", "transmission", "brand-id", "model-id"], required: [], call: (api, flags) => api.modifications.list({ ...paging(flags), year_from: flags.year_from, year_to: flags.year_to, body: flags.body, fuel: flags.fuel, drive: flags.drive, transmission: flags.transmission, brand_id: flags.brand_id, model_id: flags.model_id }) },
  { words: ["modifications", "get"], allow: ["id"], required: ["id"], call: (api, flags) => api.modifications.get(flags.id ?? "") },
  { words: ["modifications", "specs"], allow: ["id"], required: ["id"], call: (api, flags) => api.modifications.specs(flags.id ?? "") },
  { words: ["modifications", "equipment"], allow: ["id"], required: ["id"], call: (api, flags) => api.modifications.equipment(flags.id ?? "") },
  { words: ["engines", "list"], allow: ["limit", "page", "q", "sort", "direction", "modified-after", "fuel", "brand-id", "model-id", "volume-from", "volume-to"], required: [], call: (api, flags) => api.engines.list({ ...paging(flags), fuel: flags.fuel, brand_id: flags.brand_id, model_id: flags.model_id, volume_from: flags.volume_from, volume_to: flags.volume_to }) },
  { words: ["bodies", "list"], allow: ["limit", "page", "q", "sort", "direction"], required: [], call: (api, flags) => api.bodies.list(bodiesQuery(flags)) },
  { words: ["fuel", "list"], allow: ["limit", "page", "q", "sort", "direction", "modified-after", "year-from", "year-to", "body", "fuel", "drive", "transmission", "brand-id", "model-id"], required: [], call: (api, flags) => api.fuel.list({ ...paging(flags), year_from: flags.year_from, year_to: flags.year_to, body: flags.body, fuel: flags.fuel, drive: flags.drive, transmission: flags.transmission, brand_id: flags.brand_id, model_id: flags.model_id }) },
  { words: ["colors", "list"], allow: ["limit", "page", "q", "sort", "direction", "modified-after", "brand-id", "model-id"], required: [], call: (api, flags) => api.colors.list({ ...paging(flags), brand_id: flags.brand_id, model_id: flags.model_id }) },
  { words: ["coverage"], allow: [], required: [], call: (api) => api.coverage.get() },
];

function scrub(text: string, apiKey: string): string {
  let out = text.replace(/(?:geoapi|carapi)_sk_[A-Za-z0-9_-]+/g, "[redacted]");
  if (apiKey.length >= 8 && out.includes(apiKey)) {
    out = out.split(apiKey).join("[redacted]");
  }
  return out;
}

function fail(io: Io, apiKey: string, message: string): number {
  io.stderr.write(scrub(`${message}\n${HELP}\n`, apiKey));
  return 2;
}

function integer(value: string, name: string, min: number, max: number): number | string {
  if (!/^\d+$/.test(value)) {
    return `--${name} must be an integer.`;
  }
  const parsed = Number(value);
  if (parsed < min || parsed > max) {
    return `--${name} must be from ${min} to ${max}.`;
  }
  return parsed;
}

function text(value: string, name: string, max: number): string | string {
  if (value.length < 1 || value.length > max || /[\u0000-\u001F]/.test(value)) {
    return `--${name} must be 1 to ${max} characters.`;
  }
  return value;
}

function identifier(value: string, name: string): string | string {
  if (!/^[1-9][0-9]{0,18}$/.test(value)) {
    return `--${name} must be a positive id.`;
  }
  return value;
}

function read(values: Record<string, string | boolean | undefined>, flags: Parsed): string | undefined {
  const ints: Array<[string, keyof Parsed, number, number]> = [
    ["limit", "limit", 1, 1000],
    ["page", "page", 1, 1_000_000],
    ["year-from", "year_from", 1886, 2100],
    ["year-to", "year_to", 1886, 2100],
    ["volume-from", "volume_from", 0, 100_000],
    ["volume-to", "volume_to", 0, 100_000],
  ];
  for (const [name, key, min, max] of ints) {
    const value = values[name];
    if (typeof value !== "string") {
      continue;
    }
    const parsed = integer(value, name, min, max);
    if (typeof parsed === "string") {
      return parsed;
    }
    flags[key] = parsed as never;
  }
  const texts: Array<[string, keyof Parsed, number]> = [
    ["q", "q", 100],
    ["sort", "sort", 64],
    ["body", "body", 64],
    ["fuel", "fuel", 64],
    ["drive", "drive", 64],
    ["transmission", "transmission", 64],
    ["modified-after", "modified_after", 40],
  ];
  for (const [name, key, max] of texts) {
    const value = values[name];
    if (typeof value !== "string") {
      continue;
    }
    const parsed = text(value, name, max);
    if (parsed.startsWith("--")) {
      return parsed;
    }
    flags[key] = parsed as never;
  }
  for (const [name, key] of [["id", "id"], ["brand-id", "brand_id"], ["model-id", "model_id"]] as const) {
    const value = values[name];
    if (typeof value !== "string") {
      continue;
    }
    const parsed = identifier(value, name);
    if (parsed.startsWith("--")) {
      return parsed;
    }
    flags[key] = parsed;
  }
  const brand = values.brand;
  if (typeof brand === "string") {
    if (brand.length < 1 || brand.length > 200 || brand.includes("/") || /[\u0000-\u001F]/.test(brand)) {
      return "--brand must be a slug without a slash.";
    }
    flags.brand = brand;
  }
  const direction = values.direction;
  if (typeof direction === "string") {
    if (direction !== "asc" && direction !== "desc") {
      return "--direction must be asc or desc.";
    }
    flags.direction = direction;
  }
  const base = values["base-url"];
  if (typeof base === "string") {
    try {
      const url = new URL(base);
      if (url.protocol !== "http:" && url.protocol !== "https:") {
        return "--base-url must be an http(s) URL.";
      }
    } catch {
      return "--base-url must be an http(s) URL.";
    }
    flags.baseUrl = base;
  }
  return undefined;
}

function exitStatus(status: number): number {
  if (status === 401 || status === 403) {
    return 3;
  }
  if (status === 429) {
    return 4;
  }
  if (status === 0 || status === 503 || status >= 500) {
    return 5;
  }
  return 2;
}

function errorLine(error: unknown, apiKey: string): { status: number; line: string } {
  if (error instanceof GeoAPIError) {
    const retry = error.retryAfter === null ? "" : ` retryAfter=${error.retryAfter}`;
    return { status: error.status, line: scrub(`${error.code ?? "error"}: ${error.message}${retry}`, apiKey) };
  }
  const message = error instanceof Error ? error.message : "The request failed.";
  return { status: 503, line: scrub(`unavailable: ${message}`, apiKey) };
}

export async function main(argv: string[], io: Io): Promise<number> {
  const apiKey = io.env.GEOAPI_API_KEY ?? "";
  let positionals: string[] = [];
  let values: Record<string, string | boolean | undefined> = {};
  try {
    const parsed = parseArgs({ args: argv, options: OPTIONS, strict: true, allowPositionals: true });
    positionals = parsed.positionals;
    values = parsed.values;
  } catch (error) {
    const message = error instanceof Error ? error.message : "Invalid arguments.";
    return fail(io, apiKey, message);
  }
  if (SECRET_FLAGS.some((name) => values[name] !== undefined)) {
    return fail(io, apiKey, "Pass the key in GEOAPI_API_KEY, not on the command line.");
  }
  if (values.help === true || positionals[0] === "help") {
    io.stdout.write(scrub(`${HELP}\n`, apiKey));
    return 0;
  }
  const command = COMMANDS.find((item) => item.words.length === positionals.length && item.words.every((word, index) => word === positionals[index]));
  if (command === undefined) {
    return fail(io, apiKey, positionals.length === 0 ? "A command is required." : "Unknown command.");
  }
  for (const name of Object.keys(values)) {
    if (values[name] === undefined || name === "help" || name === "json" || name === "pretty" || name === "base-url" || name === "timeout") {
      continue;
    }
    if (!command.allow.includes(name)) {
      return fail(io, apiKey, `Unknown flag --${name}.`);
    }
  }
  for (const name of command.required) {
    if (typeof values[name] !== "string") {
      return fail(io, apiKey, `--${name} is required.`);
    }
  }
  const flags: Parsed = { pretty: values.pretty === true, timeout: 30_000 };
  const invalid = read(values, flags);
  if (invalid !== undefined) {
    return fail(io, apiKey, invalid);
  }
  const timeout = values.timeout;
  if (typeof timeout === "string") {
    const parsed = integer(timeout, "timeout", 1, 120_000);
    if (typeof parsed === "string") {
      return fail(io, apiKey, parsed);
    }
    flags.timeout = parsed;
  }
  if (apiKey === "") {
    return fail(io, apiKey, "GEOAPI_API_KEY is required.");
  }
  const api = new GeoAPI({
    apiKey,
    timeout: flags.timeout,
    retries: 2,
    ...(flags.baseUrl ? { baseUrl: flags.baseUrl } : {}),
    ...(io.fetch ? { fetch: io.fetch } : {}),
  });
  try {
    const body = await command.call(api, flags);
    const json = flags.pretty ? JSON.stringify(body, null, 2) : JSON.stringify(body);
    io.stdout.write(scrub(`${json}\n`, apiKey));
    return 0;
  } catch (error) {
    const reported = errorLine(error, apiKey);
    io.stderr.write(`${reported.line}\n`);
    return exitStatus(reported.status);
  }
}

const isMain = process.argv[1] !== undefined && import.meta.url === pathToFileURL(process.argv[1]).href;
if (isMain) {
  const code = await main(process.argv.slice(2), { stdout: process.stdout, stderr: process.stderr, env: process.env });
  process.exit(code);
}
