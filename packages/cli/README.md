# @geoapi/cli

Private command-line client for the GeoAPI catalog. Version 0.1.0 lives in this repository as `packages/cli`. It is not published on the public npm registry.

The CLI calls `@geoapi/sdk`. It does not keep a second copy of the catalog rules. Node.js 20 or newer. The same `node` entry point is the command on Linux, macOS, and Windows. This repository's CI runs it on Linux. It does not spawn a shell.

Export `GEOAPI_API_KEY` before you start the process. The key is not a flag, so it is not written into the command. `--api-key`, `--key`, and `--token` are refused, and the value is not printed. stdout is only the JSON document. Errors, including the API code, go to stderr.

```sh
node /absolute/path/to/GEoAPI/packages/cli/dist/index.js brands list --limit 20 --json
node /absolute/path/to/GEoAPI/packages/cli/dist/index.js models list --brand bmw
node /absolute/path/to/GEoAPI/packages/cli/dist/index.js modifications list --model-id 314 --year-from 2020
node /absolute/path/to/GEoAPI/packages/cli/dist/index.js coverage
```

Use the absolute path. A host does not start in this repository. `--json` writes one compact JSON document. `--pretty` indents it. The default is compact. Rows stay in the API's order.

`--base-url` overrides `https://geoapi.ge`; it must be https (plain http only for localhost) because the key is sent with every request. The process sets its exit code instead of calling `process.exit`, so large output piped to `jq` is not cut off. `--timeout` is milliseconds and defaults to 30000. `--limit` and `--page` are sent to the SDK.

Exit codes: 0 success, 2 invalid input (including 400, 404, 405, and 422), 3 auth (401 and 403), 4 rate limited (429), 5 network or service (including 503). 429 and 503 retry through the SDK at most twice. `Retry-After` of 0 does not wait. A missing `Retry-After` waits 250 ms times the attempt.

`geoapi_sk_` and `carapi_sk_` text is replaced with `[redacted]` on stdout and stderr. `npm test` uses a mock `fetch`. It does not call production.
