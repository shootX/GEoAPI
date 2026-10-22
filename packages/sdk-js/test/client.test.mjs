import assert from "node:assert/strict";
import http from "node:http";
import { once } from "node:events";
import test from "node:test";

import { GeoAPI, GeoAPIError } from "../dist/index.js";

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
    meta: { page: 1, per_page: 1, total: 1 },
    collection: { count: 1, total: 1, pages: 1, next: null, prev: null, first: "/first", last: "/last" },
  };
}

function client(fetch, options = {}) {
  return new GeoAPI({ apiKey: KEY, fetch, timeout: 1000, ...options });
}

test("calls every catalog route and keeps the key out of the URL", async () => {
  const seen = [];
  const fetch = async (url, init) => {
    seen.push({ url: String(url), authorization: init.headers.Authorization, method: init.method });
    const path = new URL(url).pathname;
    if (path.endsWith("/specs")) {
      return jsonResponse(200, { data: { modification_id: "12345" } });
    }
    if (path.endsWith("/equipment")) {
      return jsonResponse(200, { data: { modification_id: "12345", standard: [], optional: [], not_available: [], unknown: [] } });
    }
    if (path === "/api/v1/modifications/12345") {
      return jsonResponse(200, { data: { id: "12345", name: "2.5" } });
    }
    if (path === "/api/v1/coverage") {
      return jsonResponse(200, { brands_count: 1, models_count: 1 });
    }
    return jsonResponse(200, listBody({ id: "1" }));
  };
  const api = client(fetch);
  const signal = new AbortController().signal;
  await api.brands.list({ limit: 20, signal });
  await api.models.byBrand("bmw");
  await api.models.byBrand("a b");
  await api.years.byModel("314");
  await api.generations.byModel("314");
  await api.generations.modifications("100");
  const found = await api.modifications.list({ model_id: "314", year_from: 2020 });
  const detail = await api.modifications.get("12345");
  await api.modifications.specs("12345");
  await api.modifications.equipment("12345");
  await api.engines.list({ volume_from: 1000 });
  await api.bodies.list({ q: "sedan" });
  await api.fuel.list({ body: "sedan", drive: "fwd", transmission: "at" });
  await api.colors.list();
  const coverage = await api.coverage.get();

  assert.equal(found.data[0].id, "1");
  assert.equal(detail.data.id, "12345");
  assert.equal(coverage.brands_count, 1);
  assert.deepEqual(seen.map((call) => new URL(call.url).pathname + new URL(call.url).search), [
    "/api/v1/brands?limit=20",
    "/api/v1/brands/bmw/models",
    "/api/v1/brands/a%20b/models",
    "/api/v1/models/314/years",
    "/api/v1/models/314/generations",
    "/api/v1/generations/100/modifications",
    "/api/v1/modifications?model_id=314&year_from=2020",
    "/api/v1/modifications/12345",
    "/api/v1/modifications/12345/specs",
    "/api/v1/modifications/12345/equipment",
    "/api/v1/engines?volume_from=1000",
    "/api/v1/bodies?q=sedan",
    "/api/v1/fuel?body=sedan&drive=fwd&transmission=at",
    "/api/v1/colors",
    "/api/v1/coverage",
  ]);
  for (const call of seen) {
    assert.equal(call.method, "GET");
    assert.equal(call.authorization, `Bearer ${KEY}`);
    assert.equal(call.url.includes(KEY), false);
    assert.equal(call.url.includes("signal"), false);
  }
});

test("does not retry 400, 401, 403, 404, 405, or 422", async () => {
  for (const status of [400, 401, 403, 404, 405, 422]) {
    let calls = 0;
    const fetch = async () => {
      calls += 1;
      return jsonResponse(status, { error: { code: "sample", message: `status ${status} ${KEY}` } });
    };
    await assert.rejects(client(fetch).brands.list(), (error) => {
      assert.ok(error instanceof GeoAPIError);
      assert.equal(error.status, status);
      assert.equal(error.code, "sample");
      assert.equal(error.requestId, null);
      assert.equal(error.message.includes(KEY), false);
      assert.match(error.message, /\[redacted\]/);
      return true;
    });
    assert.equal(calls, 1, String(status));
  }
});

test("retries 429 and 503 twice when Retry-After is zero", async () => {
  for (const status of [429, 503]) {
    let calls = 0;
    const fetch = async () => {
      calls += 1;
      return jsonResponse(status, { error: { code: status === 429 ? "RATE_LIMIT_EXCEEDED" : "unavailable", message: "later" } }, { "Retry-After": "0" });
    };
    await assert.rejects(client(fetch).brands.list(), (error) => {
      assert.equal(error.status, status);
      assert.equal(error.requestId, null);
      return true;
    });
    assert.equal(calls, 3, String(status));
  }
});

test("a missing Retry-After backs off briefly and a later 200 is returned", async () => {
  let calls = 0;
  const started = Date.now();
  const fetch = async () => {
    calls += 1;
    if (calls === 1) {
      return jsonResponse(503, { error: { code: "unavailable", message: "down" } });
    }
    return jsonResponse(200, listBody({ id: "9" }));
  };
  const rows = await client(fetch).brands.list();
  assert.equal(rows.data[0].id, "9");
  assert.equal(calls, 2);
  assert.ok(Date.now() - started < 500);
});

test("caps Retry-After at 2 seconds", { timeout: 10_000 }, async () => {
  let calls = 0;
  const started = Date.now();
  const fetch = async () => {
    calls += 1;
    if (calls === 1) {
      return jsonResponse(429, { error: { code: "RATE_LIMIT_EXCEEDED", message: "wait" } }, { "Retry-After": "9" });
    }
    return jsonResponse(200, listBody({ id: "2" }));
  };
  await client(fetch).brands.list();
  const elapsed = Date.now() - started;
  assert.equal(calls, 2);
  assert.ok(elapsed >= 1900, String(elapsed));
  assert.ok(elapsed < 2800, String(elapsed));
});

test("refuses a secret key in the browser before any request", () => {
  globalThis.window = {};
  let calls = 0;
  try {
    assert.throws(
      () => new GeoAPI({ apiKey: KEY, fetch: async () => { calls += 1; return jsonResponse(200, {}); } }),
      (error) => {
        assert.ok(error instanceof GeoAPIError);
        assert.equal(error.message, "A secret key cannot be used in the browser.");
        assert.equal(error.message.includes(KEY), false);
        assert.equal(error.requestId, null);
        return true;
      },
    );
    assert.throws(() => new GeoAPI({ apiKey: "carapi_sk_legacy_secret", fetch: async () => jsonResponse(200, {}) }), GeoAPIError);
    const publishable = new GeoAPI({ apiKey: "geoapi_pk_demo", fetch: async () => jsonResponse(200, {}) });
    assert.ok(publishable);
    assert.equal(calls, 0);
  } finally {
    delete globalThis.window;
  }
});

test("refuses a plain-http base URL except for this machine", () => {
  const fetch = async () => jsonResponse(200, {});
  assert.throws(() => new GeoAPI({ apiKey: KEY, baseUrl: "http://geoapi.ge", fetch }), (error) => {
    assert.ok(error instanceof GeoAPIError);
    assert.equal(error.message, "baseUrl must use https (http only for localhost).");
    return true;
  });
  assert.throws(() => new GeoAPI({ apiKey: KEY, baseUrl: "ftp://geoapi.ge", fetch }), GeoAPIError);
  assert.ok(new GeoAPI({ apiKey: KEY, baseUrl: "http://127.0.0.1:8080", fetch }));
  assert.ok(new GeoAPI({ apiKey: KEY, baseUrl: "https://staging.geoapi.ge/", fetch }));
});

test("aborts when the caller signal or the timeout fires", async () => {
  const hung = (_url, init) => new Promise((_resolve, reject) => {
    if (init.signal?.aborted) {
      reject(init.signal.reason);
      return;
    }
    init.signal.addEventListener("abort", () => reject(init.signal.reason));
  });
  const controller = new AbortController();
  controller.abort();
  await assert.rejects(client(hung).brands.list({ signal: controller.signal }));

  const started = Date.now();
  await assert.rejects(client(hung, { timeout: 40 }).brands.list());
  assert.ok(Date.now() - started < 1000);
});

test("uses the caller fetch and a trailing-slash base URL", async () => {
  let calls = 0;
  const fetch = async (url) => {
    calls += 1;
    assert.equal(url, "https://example.test/api/v1/brands");
    return jsonResponse(200, listBody({ id: "1" }));
  };
  await client(fetch, { baseUrl: "https://example.test/" }).brands.list();
  assert.equal(calls, 1);
});

test("reads a local HTTP server without calling production", async () => {
  const server = http.createServer((incoming, response) => {
    assert.equal(incoming.url, "/api/v1/brands?limit=1");
    assert.equal(incoming.headers.authorization, "Bearer local-key");
    response.setHeader("content-type", "application/json");
    response.end(JSON.stringify(listBody({ id: "314", name: "local" })));
  });
  server.listen(0, "127.0.0.1");
  await once(server, "listening");
  try {
    const address = server.address();
    const api = new GeoAPI({
      apiKey: "local-key",
      baseUrl: `http://127.0.0.1:${address.port}`,
      timeout: 1000,
    });
    const brands = await api.brands.list({ limit: 1 });
    assert.equal(brands.data[0].id, "314");
  } finally {
    server.close();
  }
});

test("exposes Retry-After seconds on the final 429 error", async () => {
  const fetch = async () => jsonResponse(429, { error: { code: "RATE_LIMIT_EXCEEDED", message: "wait" } }, { "Retry-After": "0" });
  await assert.rejects(client(fetch, { retries: 0 }).brands.list(), (error) => {
    assert.equal(error.retryAfter, 0);
    return true;
  });
  const notFound = async () => jsonResponse(404, { error: { code: "not_found", message: "x" } }, { "Retry-After": "5" });
  await assert.rejects(client(notFound).brands.list(), (error) => {
    assert.equal(error.retryAfter, null);
    return true;
  });
});

test("refuses a secret key in a web worker", () => {
  globalThis.importScripts = () => {};
  try {
    assert.throws(() => new GeoAPI({ apiKey: KEY, fetch: async () => jsonResponse(200, {}) }), GeoAPIError);
  } finally {
    delete globalThis.importScripts;
  }
});
