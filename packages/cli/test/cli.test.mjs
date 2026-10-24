import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import test from "node:test";
import { fileURLToPath } from "node:url";

import { HELP, main } from "../dist/index.js";

const KEY = "geoapi_sk_selector_secret";
const ROOT = fileURLToPath(new URL("..", import.meta.url));

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

function capture(fetch, env = { GEOAPI_API_KEY: KEY }) {
  const out = { stdout: "", stderr: "" };
  return {
    out,
    io: {
      stdout: { write: (chunk) => { out.stdout += chunk; } },
      stderr: { write: (chunk) => { out.stderr += chunk; } },
      env,
      fetch,
    },
  };
}

test("help is a stable snapshot and does not need a key", async () => {
  const { out, io } = capture(async () => {
    throw new Error("fetch");
  }, {});
  assert.equal(await main(["--help"], io), 0);
  assert.equal(out.stdout, `${HELP}\n`);
  assert.equal(out.stderr, "");
  assert.match(out.stdout, /brands list/);
  assert.match(out.stdout, /coverage/);
  assert.equal(out.stdout.includes("geoapi_sk_"), false);
});

test("brands list --json is a compact snapshot and keeps the key out of the URL", async () => {
  const body = listBody({ id: "1", slug: "bmw", name: "BMW" });
  const seen = [];
  const { out, io } = capture(async (url, init) => {
    seen.push({ url: String(url), authorization: init.headers.Authorization });
    return jsonResponse(200, body);
  });
  const code = await main(["brands", "list", "--limit", "20", "--page", "2", "--json"], io);
  assert.equal(code, 0);
  assert.equal(out.stderr, "");
  assert.equal(out.stdout, `${JSON.stringify(body)}\n`);
  assert.equal(JSON.parse(out.stdout).data[0].name, "BMW");
  const url = new URL(seen[0].url);
  assert.equal(url.pathname, "/api/v1/brands");
  assert.equal(url.searchParams.get("limit"), "20");
  assert.equal(url.searchParams.get("page"), "2");
  assert.equal(seen[0].authorization, `Bearer ${KEY}`);
  assert.equal(seen[0].url.includes(KEY), false);
  assert.equal(out.stdout.includes(KEY), false);
});

test("--pretty indents and a second run matches the first", async () => {
  const body = listBody({ id: "1", slug: "bmw", name: "BMW" });
  const run = () => {
    const captured = capture(async () => jsonResponse(200, body));
    return main(["brands", "list", "--pretty"], captured.io).then((code) => ({ code, out: captured.out }));
  };
  const [first, second] = await Promise.all([run(), run()]);
  assert.equal(first.code, 0);
  assert.equal(first.out.stdout, second.out.stdout);
  assert.equal(first.out.stdout, `${JSON.stringify(body, null, 2)}\n`);
});

test("models, modifications, and coverage call the SDK", async () => {
  const seen = [];
  const { out, io } = capture(async (url) => {
    seen.push(String(url));
    const path = new URL(url).pathname;
    if (path === "/api/v1/coverage") return jsonResponse(200, { brands_count: 314 });
    if (path === "/api/v1/modifications") return jsonResponse(200, listBody({ id: "1000", name: "320i" }));
    return jsonResponse(200, listBody({ id: "314", slug: "3-series", name: "3 Series" }));
  });
  assert.equal(await main(["models", "list", "--brand", "bmw"], io), 0);
  assert.match(out.stdout, /3 Series/);
  out.stdout = "";
  assert.equal(await main(["modifications", "list", "--model-id", "314", "--year-from", "2020"], io), 0);
  assert.match(out.stdout, /320i/);
  out.stdout = "";
  assert.equal(await main(["coverage"], io), 0);
  assert.equal(out.stdout, `${JSON.stringify({ brands_count: 314 })}\n`);
  assert.equal(new URL(seen[0]).pathname, "/api/v1/brands/bmw/models");
  const modifications = new URL(seen[1]);
  assert.equal(modifications.pathname, "/api/v1/modifications");
  assert.equal(modifications.searchParams.get("model_id"), "314");
  assert.equal(modifications.searchParams.get("year_from"), "2020");
  assert.equal(new URL(seen[2]).pathname, "/api/v1/coverage");
});

test("invalid input exits 2 before a request", async () => {
  let calls = 0;
  const { out, io } = capture(async () => {
    calls += 1;
    return jsonResponse(200, listBody({ id: "1" }));
  });
  assert.equal(await main(["models", "list"], io), 2);
  assert.equal(await main(["brands", "list", "--limit", "0"], io), 2);
  assert.equal(await main(["brands", "list", "--api-key", KEY], io), 2);
  assert.equal(await main([], io), 2);
  assert.equal(calls, 0);
  assert.equal(out.stdout, "");
  assert.equal(out.stderr.includes(KEY), false);
  assert.match(out.stderr, /--brand is required/);
  assert.match(out.stderr, /GEOAPI_API_KEY, not on the command line/);
});

test("a wrong token exits 3 and redacts every geoapi_sk_ key", async () => {
  let calls = 0;
  const other = "geoapi_sk_other_secret";
  const { out, io } = capture(async () => {
    calls += 1;
    return jsonResponse(401, { error: { code: "unauthorized", message: `denied ${KEY} ${other}` } });
  });
  assert.equal(await main(["brands", "list"], io), 3);
  assert.equal(calls, 1);
  assert.equal(out.stdout, "");
  assert.match(out.stderr, /^unauthorized: denied \[redacted\] \[redacted\]\n/);
  assert.equal(out.stderr.includes(KEY), false);
  assert.equal(out.stderr.includes(other), false);
});

test("429 retries twice and then exits 4", async () => {
  let calls = 0;
  const { out, io } = capture(async () => {
    calls += 1;
    return jsonResponse(429, { error: { code: "RATE_LIMIT_EXCEEDED", message: "slow down" } }, { "Retry-After": "0" });
  });
  assert.equal(await main(["brands", "list", "--limit", "1"], io), 4);
  assert.equal(calls, 3);
  assert.equal(out.stdout, "");
  assert.match(out.stderr, /^RATE_LIMIT_EXCEEDED: slow down retryAfter=0\n/);
  assert.equal(out.stderr.includes(KEY), false);
});

test("a 429 that recovers exits 0 without a fourth call", async () => {
  let calls = 0;
  const body = listBody({ id: "1", name: "BMW" });
  const { out, io } = capture(async () => {
    calls += 1;
    if (calls < 3) {
      return jsonResponse(429, { error: { code: "RATE_LIMIT_EXCEEDED", message: "slow down" } }, { "Retry-After": "0" });
    }
    return jsonResponse(200, body);
  });
  assert.equal(await main(["brands", "list"], io), 0);
  assert.equal(calls, 3);
  assert.equal(out.stdout, `${JSON.stringify(body)}\n`);
  assert.equal(out.stderr, "");
});

test("503 exits 5 and a network error does not print the key", async () => {
  let calls = 0;
  const limited = capture(async () => {
    calls += 1;
    return jsonResponse(503, { error: { code: "unavailable", message: "down" } }, { "Retry-After": "0" });
  });
  assert.equal(await main(["coverage"], limited.io), 5);
  assert.equal(calls, 3);
  assert.match(limited.out.stderr, /^unavailable: down retryAfter=0\n/);

  const { out, io } = capture(async () => {
    throw new Error(`socket ${KEY} geoapi_sk_other_secret`);
  });
  assert.equal(await main(["coverage"], io), 5);
  assert.equal(out.stdout, "");
  assert.match(out.stderr, /^unavailable: socket \[redacted\] \[redacted]\n/);
  assert.equal(out.stderr.includes(KEY), false);
});

test("stdout redacts a secret that the payload echoed", async () => {
  const { out, io } = capture(async () => jsonResponse(200, { data: [{ name: `${KEY} geoapi_sk_other_secret` }] }));
  assert.equal(await main(["brands", "list", "--json"], io), 0);
  assert.match(out.stdout, /\[redacted\] \[redacted\]/);
  assert.equal(out.stdout.includes(KEY), false);
  assert.equal(out.stdout.includes("geoapi_sk_"), false);
});

test("the executable help and a missing key do not call the network", () => {
  const help = spawnSync(process.execPath, ["dist/index.js", "--help"], {
    cwd: ROOT,
    env: { ...process.env, GEOAPI_API_KEY: "" },
    encoding: "utf8",
  });
  assert.equal(help.status, 0);
  assert.equal(help.stdout, `${HELP}\n`);
  assert.equal(help.stderr, "");

  const missing = spawnSync(process.execPath, ["dist/index.js", "brands", "list", "--limit", "20", "--json"], {
    cwd: ROOT,
    env: { ...process.env, GEOAPI_API_KEY: "" },
    encoding: "utf8",
  });
  assert.equal(missing.status, 2);
  assert.equal(missing.stdout, "");
  assert.match(missing.stderr, /GEOAPI_API_KEY is required/);
});

test("refuses plain http base URLs except localhost", async () => {
  const io = () => { const out = { stdout: "", stderr: "" }; return [out, { stdout: { write: (c) => { out.stdout += c; } }, stderr: { write: (c) => { out.stderr += c; } }, env: { GEOAPI_API_KEY: "geoapi_sk_test_secret" }, fetch: async () => new Response(JSON.stringify({ brands_count: 1 }), { status: 200, headers: { "content-type": "application/json" } }) }]; };
  let [out, env] = io();
  assert.equal(await main(["coverage", "--base-url", "http://example.com"], env), 2);
  assert.match(out.stderr, /https/);
  [out, env] = io();
  assert.equal(await main(["coverage", "--base-url", "http://127.0.0.1:8080"], env), 0);
});

test("the executable sets the exit code without process.exit and writes full help", async () => {
  const { spawnSync } = await import("node:child_process");
  const { fileURLToPath } = await import("node:url");
  const entry = fileURLToPath(new URL("../dist/index.js", import.meta.url));
  const r = spawnSync(process.execPath, [entry, "--help"], { encoding: "utf8" });
  assert.equal(r.status, 0);
  assert.match(r.stdout, /geoapi <command>/);
});
