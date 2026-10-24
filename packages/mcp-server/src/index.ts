#!/usr/bin/env node
import { pathToFileURL } from "node:url";

import { GeoAPI, GeoAPIError } from "@geoapi/sdk";
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

const limit = z.number().int().min(1).max(100).optional();
const page = z.number().int().min(1).max(1_000_000).optional();
const id = z.string().regex(/^[1-9][0-9]{0,18}$/);
const slug = z.string().min(1).max(200).regex(/^[^/]+$/);
const q = z.string().min(1).max(100).optional();
const filter = z.string().min(1).max(64).optional();
const year = z.number().int().min(1886).max(2100).optional();

const readOnly = { readOnlyHint: true, destructiveHint: false, openWorldHint: true } as const;

function scrub(message: string): string {
  return message.replace(/geoapi_sk_[A-Za-z0-9_-]+/g, "[redacted]");
}

function ok(value: unknown) {
  return { content: [{ type: "text" as const, text: JSON.stringify(value) }] };
}

function fail(error: unknown) {
  if (error instanceof GeoAPIError) {
    return {
      isError: true as const,
      content: [{
        type: "text" as const,
        text: JSON.stringify({
          status: error.status,
          code: error.code,
          message: scrub(error.message),
          retryAfter: error.retryAfter,
          requestId: error.requestId,
        }),
      }],
    };
  }
  const message = error instanceof Error ? error.message : "The tool failed.";
  return {
    isError: true as const,
    content: [{
      type: "text" as const,
      text: JSON.stringify({ status: null, code: null, message: scrub(message), retryAfter: null, requestId: null }),
    }],
  };
}

function paged(args: { limit?: number; page?: number }): { limit: number; page?: number } {
  return { limit: args.limit ?? 20, page: args.page };
}

export function createServer(api: GeoAPI): McpServer {
  const server = new McpServer({ name: "geoapi", version: "0.1.0" });

  server.registerTool("geoapi_list_brands", {
    description: "Read-only brand list. limit defaults to 20 and cannot exceed 100.",
    inputSchema: z.object({ limit, page, q }).strict(),
    annotations: readOnly,
  }, async (args) => {
    try {
      return ok(await api.brands.list({ ...paged(args), q: args.q }));
    } catch (error) {
      return fail(error);
    }
  });

  server.registerTool("geoapi_list_models", {
    description: "Read-only models for one brand slug.",
    inputSchema: z.object({ slug, limit, page, q, body: filter }).strict(),
    annotations: readOnly,
  }, async (args) => {
    try {
      return ok(await api.models.byBrand(args.slug, { ...paged(args), q: args.q, body: args.body }));
    } catch (error) {
      return fail(error);
    }
  });

  server.registerTool("geoapi_list_generations", {
    description: "Read-only generations for one model id. The id is a string.",
    inputSchema: z.object({ model_id: id, limit, page, year_from: year, year_to: year, body: filter }).strict(),
    annotations: readOnly,
  }, async (args) => {
    try {
      return ok(await api.generations.byModel(args.model_id, {
        ...paged(args),
        year_from: args.year_from,
        year_to: args.year_to,
        body: args.body,
      }));
    } catch (error) {
      return fail(error);
    }
  });

  server.registerTool("geoapi_list_years", {
    description: "Read-only years for one model id. The id is a string.",
    inputSchema: z.object({ model_id: id, limit, page }).strict(),
    annotations: readOnly,
  }, async (args) => {
    try {
      return ok(await api.years.byModel(args.model_id, paged(args)));
    } catch (error) {
      return fail(error);
    }
  });

  server.registerTool("geoapi_list_modifications", {
    description: "Read-only modification search. Ids are strings. limit defaults to 20 and cannot exceed 100.",
    inputSchema: z.object({
      limit,
      page,
      q,
      brand_id: id.optional(),
      model_id: id.optional(),
      year_from: year,
      year_to: year,
      body: filter,
      fuel: filter,
      drive: filter,
      transmission: filter,
    }).strict(),
    annotations: readOnly,
  }, async (args) => {
    try {
      return ok(await api.modifications.list({
        ...paged(args),
        q: args.q,
        brand_id: args.brand_id,
        model_id: args.model_id,
        year_from: args.year_from,
        year_to: args.year_to,
        body: args.body,
        fuel: args.fuel,
        drive: args.drive,
        transmission: args.transmission,
      }));
    } catch (error) {
      return fail(error);
    }
  });

  server.registerTool("geoapi_get_modification", {
    description: "Read-only one modification. The id is a string.",
    inputSchema: z.object({ id }).strict(),
    annotations: readOnly,
  }, async (args) => {
    try {
      return ok(await api.modifications.get(args.id));
    } catch (error) {
      return fail(error);
    }
  });

  server.registerTool("geoapi_get_specs", {
    description: "Read-only stored specs for one modification. dimensions and performance are null.",
    inputSchema: z.object({ id }).strict(),
    annotations: readOnly,
  }, async (args) => {
    try {
      return ok(await api.modifications.specs(args.id));
    } catch (error) {
      return fail(error);
    }
  });

  server.registerTool("geoapi_get_equipment", {
    description: "Read-only equipment groups for one modification.",
    inputSchema: z.object({ id }).strict(),
    annotations: readOnly,
  }, async (args) => {
    try {
      return ok(await api.modifications.equipment(args.id));
    } catch (error) {
      return fail(error);
    }
  });

  server.registerTool("geoapi_get_coverage", {
    description: "Read-only catalog counts. This is not a quota.",
    inputSchema: z.object({}).strict(),
    annotations: readOnly,
  }, async () => {
    try {
      return ok(await api.coverage.get());
    } catch (error) {
      return fail(error);
    }
  });

  return server;
}

async function main(): Promise<void> {
  const apiKey = process.env.GEOAPI_API_KEY ?? "";
  if (apiKey === "") {
    console.error("GEOAPI_API_KEY is required");
    process.exit(1);
  }
  const api = new GeoAPI({
    apiKey,
    ...(process.env.GEOAPI_BASE_URL ? { baseUrl: process.env.GEOAPI_BASE_URL } : {}),
  });
  await createServer(api).connect(new StdioServerTransport());
}

const isMain = process.argv[1] !== undefined && import.meta.url === pathToFileURL(process.argv[1]).href;
if (isMain) {
  await main();
}
