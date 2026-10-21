import { pathToFileURL } from "node:url";

import { GeoAPI } from "../dist/index.js";

export function reportFailure(name, error) {
  const status = error && typeof error === "object" && "status" in error ? error.status : "";
  const code = error && typeof error === "object" && "code" in error ? error.code : "";
  const retryAfter = error && typeof error === "object" && "retryAfter" in error ? error.retryAfter : "";
  const message = error instanceof Error ? error.message : String(error);
  const text = `smoke failed at ${name}: status=${status} code=${code} retryAfter=${retryAfter} ${message}`
    .replace(/geoapi_sk_[A-Za-z0-9_]+/g, "[redacted]");
  const wrapped = new Error(text);
  wrapped.step = name;
  return wrapped;
}

export async function runSteps(steps) {
  for (const step of steps) {
    try {
      await step.run();
    } catch (error) {
      throw reportFailure(step.name, error);
    }
  }
}

export function catalogSteps(api) {
  return [
    { name: "brands.list", run: () => api.brands.list({ limit: 20 }) },
    { name: "models.byBrand", run: () => api.models.byBrand("bmw") },
    { name: "generations.byModel", run: () => api.generations.byModel("314") },
    { name: "modifications.list", run: () => api.modifications.list({ model_id: "314", year_from: 2020 }) },
    { name: "modifications.get", run: () => api.modifications.get("12345") },
  ];
}

const isMain = process.argv[1] !== undefined && import.meta.url === pathToFileURL(process.argv[1]).href;
if (isMain) {
  const apiKey = process.env.GEOAPI_API_KEY ?? "";
  if (apiKey === "") {
    console.error("smoke failed before any call: GEOAPI_API_KEY is required");
    process.exit(1);
  }
  const api = new GeoAPI({
    apiKey,
    ...(process.env.GEOAPI_BASE_URL ? { baseUrl: process.env.GEOAPI_BASE_URL } : {}),
  });
  try {
    await runSteps(catalogSteps(api));
    console.log("smoke ok");
  } catch (error) {
    console.error(error instanceof Error ? error.message : String(error));
    process.exit(1);
  }
}
