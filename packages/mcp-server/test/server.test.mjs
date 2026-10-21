import assert from "node:assert/strict";
import test from "node:test";

import { Client } from "@modelcontextprotocol/sdk/client/index.js";
import { InMemoryTransport } from "@modelcontextprotocol/sdk/inMemory.js";
import { GeoAPI } from "@geoapi/sdk";

import { createServer } from "../dist/index.js";

const KEY = "geoapi_sk_selector_secret";

function jsonResponse(status, body, headers = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "content-type": "application/json", ...headers },
  });
}

function listBody(row) {
  return {
    data: [row],
    meta: { page: 1, per_page: 20, total: 1 },
    collection: { count: 1, total: 1, pages: 1, next: null, prev: null, first: "/first", last: "/last" },
  };
}

function textOf(result) {
  return result.content.map((part) => part.text).join("\n");
}

async function session(api) {
  const [clientTransport, serverTransport] = InMemoryTransport.createLinkedPair();
  await createServer(api).connect(serverTransport);
  const client = new Client({ name: "geoapi-test", version: "0.0.0" });
  await client.connect(clientTransport);
  return client;
}

test("initializes, lists the read-only tools, and calls each one", async () => {
  const seen = [];
  const fetch = async (url, init) => {
    seen.push({ url: String(url), authorization: init.headers.Authorization });
    const path = new URL(url).pathname;
    if (path.endsWith("/specs")) return jsonResponse(200, { data: { modification_id: "12345" } });
    if (path.endsWith("/equipment")) return jsonResponse(200, { data: { modification_id: "12345", standard: [] } });
    if (path === "/api/v1/modifications/12345") return jsonResponse(200, { data: { id: "12345", name: "320i" } });
    if (path === "/api/v1/coverage") return jsonResponse(200, { brands_count: 314 });
    if (path === "/api/v1/brands/bmw/models") return jsonResponse(200, listBody({ id: "314", slug: "3-series", name: "3 Series" }));
    if (path === "/api/v1/models/314/generations") return jsonResponse(200, listBody({ id: "100", name: "G20", model_id: "314" }));
    if (path === "/api/v1/models/314/years") return jsonResponse(200, listBody({ id: "2020", year: 2020 }));
    return jsonResponse(200, listBody({ id: "1", slug: "bmw", name: "BMW" }));
  };
  const client = await session(new GeoAPI({ apiKey: KEY, fetch, timeout: 1000 }));
  const listed = await client.listTools();
  const names = listed.tools.map((tool) => tool.name).sort();
  assert.deepEqual(names, [
    "geoapi_get_coverage",
    "geoapi_get_equipment",
    "geoapi_get_modification",
    "geoapi_get_specs",
    "geoapi_list_brands",
    "geoapi_list_generations",
    "geoapi_list_models",
    "geoapi_list_modifications",
    "geoapi_list_years",
  ]);
  for (const tool of listed.tools) {
    const encoded = JSON.stringify(tool.inputSchema);
    assert.equal(encoded.includes("apiKey"), false);
    assert.equal(encoded.toLowerCase().includes("sql"), false);
    assert.equal(tool.annotations.readOnlyHint, true);
  }

  const brands = await client.callTool({ name: "geoapi_list_brands", arguments: { limit: 20 } });
  const models = await client.callTool({ name: "geoapi_list_models", arguments: { slug: "bmw" } });
  const generations = await client.callTool({ name: "geoapi_list_generations", arguments: { model_id: "314" } });
  await client.callTool({ name: "geoapi_list_years", arguments: { model_id: "314" } });
  await client.callTool({ name: "geoapi_list_modifications", arguments: { model_id: "314", year_from: 2020 } });
  await client.callTool({ name: "geoapi_get_modification", arguments: { id: "12345" } });
  await client.callTool({ name: "geoapi_get_specs", arguments: { id: "12345" } });
  await client.callTool({ name: "geoapi_get_equipment", arguments: { id: "12345" } });
  const coverage = await client.callTool({ name: "geoapi_get_coverage", arguments: {} });

  assert.match(textOf(brands), /BMW/);
  assert.match(textOf(models), /3 Series/);
  assert.match(textOf(generations), /G20/);
  assert.match(textOf(coverage), /314/);
  assert.deepEqual(seen.map((call) => new URL(call.url).pathname), [
    "/api/v1/brands",
    "/api/v1/brands/bmw/models",
    "/api/v1/models/314/generations",
    "/api/v1/models/314/years",
    "/api/v1/modifications",
    "/api/v1/modifications/12345",
    "/api/v1/modifications/12345/specs",
    "/api/v1/modifications/12345/equipment",
    "/api/v1/coverage",
  ]);
  for (const call of seen) {
    assert.equal(call.authorization, `Bearer ${KEY}`);
    assert.equal(call.url.includes(KEY), false);
  }
});

test("rejects a missing slug before any request and propagates 401, 429, and 503", async () => {
  let calls = 0;
  const fetch = async () => {
    calls += 1;
    return jsonResponse(200, listBody({ id: "1" }));
  };
  const client = await session(new GeoAPI({ apiKey: KEY, fetch, timeout: 1000 }));
  const invalid = await client.callTool({ name: "geoapi_list_models", arguments: {} });
  assert.equal(invalid.isError, true);
  const unknown = await client.callTool({ name: "geoapi_list_brands", arguments: { sql: "select 1", apiKey: KEY } });
  assert.equal(unknown.isError, true);
  assert.equal(textOf(unknown).includes(KEY), false);
  assert.equal(calls, 0);

  const logs = [];
  const original = { log: console.log, error: console.error };
  console.log = (...args) => logs.push(args.join(" "));
  console.error = (...args) => logs.push(args.join(" "));
  try {
    for (const status of [401, 429, 503]) {
      let statusCalls = 0;
      const failing = async () => {
        statusCalls += 1;
        const code = status === 401 ? "unauthorized" : status === 429 ? "RATE_LIMIT_EXCEEDED" : "unavailable";
        return jsonResponse(status, { error: { code, message: `denied ${KEY}` } }, { "Retry-After": "0" });
      };
      const again = await session(new GeoAPI({ apiKey: KEY, fetch: failing, timeout: 1000 }));
      const result = await again.callTool({ name: "geoapi_list_brands", arguments: { limit: 1 } });
      const body = JSON.parse(textOf(result));
      assert.equal(result.isError, true);
      assert.equal(body.status, status);
      assert.equal(body.code, status === 401 ? "unauthorized" : status === 429 ? "RATE_LIMIT_EXCEEDED" : "unavailable");
      assert.equal(body.requestId, null);
      assert.equal(body.retryAfter, status === 401 ? null : 0);
      assert.equal(textOf(result).includes(KEY), false);
      assert.equal(statusCalls, status === 401 ? 1 : 3);
    }
  } finally {
    console.log = original.log;
    console.error = original.error;
  }
  assert.equal(logs.some((line) => line.includes(KEY)), false);
});

test("two clients keep separate keys", async () => {
  const seen = [];
  const clientFor = (key, name) => session(new GeoAPI({
    apiKey: key,
    timeout: 1000,
    fetch: async (_url, init) => {
      seen.push(init.headers.Authorization);
      return jsonResponse(200, listBody({ id: "1", name }));
    },
  }));
  const [alpha, beta] = await Promise.all([
    clientFor("geoapi_sk_alpha_secret", "Alpha"),
    clientFor("geoapi_sk_beta_secret", "Beta"),
  ]);
  const [left, right] = await Promise.all([
    alpha.callTool({ name: "geoapi_list_brands", arguments: {} }),
    beta.callTool({ name: "geoapi_list_brands", arguments: {} }),
  ]);
  assert.match(textOf(left), /Alpha/);
  assert.match(textOf(right), /Beta/);
  assert.equal(textOf(left).includes("geoapi_sk_"), false);
  assert.equal(textOf(right).includes("geoapi_sk_"), false);
  assert.deepEqual(seen.sort(), ["Bearer geoapi_sk_alpha_secret", "Bearer geoapi_sk_beta_secret"]);
});
