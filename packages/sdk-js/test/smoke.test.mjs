import assert from "node:assert/strict";
import test from "node:test";

import { reportFailure, runSteps } from "../scripts/smoke.mjs";

test("names the call that failed and does not continue", async () => {
  const ran = [];
  const secret = "geoapi_sk_selector_secret";
  await assert.rejects(
    runSteps([
      { name: "brands.list", run: async () => { ran.push("brands.list"); } },
      {
        name: "models.byBrand",
        run: async () => {
          ran.push("models.byBrand");
          throw Object.assign(new Error(`nope ${secret}`), { status: 500, code: null, retryAfter: null });
        },
      },
      { name: "generations.byModel", run: async () => { ran.push("generations.byModel"); } },
    ]),
    (error) => {
      assert.equal(error.step, "models.byBrand");
      assert.match(error.message, /^smoke failed at models\.byBrand: status=500 code=null retryAfter=null /);
      assert.equal(error.message.includes(secret), false);
      assert.match(error.message, /\[redacted\]/);
      return true;
    },
  );
  assert.deepEqual(ran, ["brands.list", "models.byBrand"]);
});

test("reportFailure keeps a non-Error throw attached to the call", () => {
  const error = reportFailure("modifications.get", "socket hang up");
  assert.equal(error.step, "modifications.get");
  assert.match(error.message, /^smoke failed at modifications\.get:/);
  assert.match(error.message, /socket hang up/);
});
