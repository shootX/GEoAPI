# @geoapi/mcp-server

Private stdio MCP server for the GeoAPI catalog. Version 0.1.0 lives in this repository as `packages/mcp-server`. It is not on the public npm registry.

The server calls `@geoapi/sdk`. It does not keep a second copy of the catalog rules and it is not a Laravel route. Node.js 20 or newer. The key is `GEOAPI_API_KEY` in the host environment. A tool argument cannot set it.

`cursor.example.json` uses the placeholder `geoapi_sk_…`. Replace that only in the host config, not in the repository.

Tools, all read-only:

- `geoapi_list_brands`
- `geoapi_list_models` (`slug`)
- `geoapi_list_generations` (`model_id`)
- `geoapi_list_years` (`model_id`)
- `geoapi_list_modifications`
- `geoapi_get_modification` (`id`)
- `geoapi_get_specs` (`id`)
- `geoapi_get_equipment` (`id`)
- `geoapi_get_coverage`

`limit` defaults to 20 and cannot exceed 100. There is no photo tool: this API has no photo list.

A prompt such as "List BMW models and one generation of model 314" calls `geoapi_list_models` and `geoapi_list_generations`. `npm test` answers that prompt with a mock server. It does not call production.

401 is not retried. 429 and 503 follow the SDK retry. The tool error then has `status`, `code`, `message`, `retryAfter`, and `requestId`. `requestId` is null because the API does not send one.

## Build

```sh
cd packages/sdk-js && npm ci && npm run build
cd ../mcp-server && npm ci && npm run build
```

`GEOAPI_BASE_URL` overrides `https://geoapi.ge`. Hosts start the server with their own working directory, so always give the **absolute** path to `dist/index.js`.

## Connect from Cursor

`~/.cursor/mcp.json` (or `.cursor/mcp.json` in a project), see `cursor.example.json`:

```json
{
  "mcpServers": {
    "geoapi": {
      "command": "node",
      "args": ["/absolute/path/to/GEoAPI/packages/mcp-server/dist/index.js"],
      "env": { "GEOAPI_API_KEY": "geoapi_sk_…" }
    }
  }
}
```

## Connect from Claude Desktop

Settings → Developer → Edit Config opens `claude_desktop_config.json` (macOS `~/Library/Application Support/Claude/`, Windows `%APPDATA%\Claude\`). Add the same `mcpServers.geoapi` block as above and restart Claude Desktop.

Use a key from a project made for this purpose, so you can revoke it in the cabinet without affecting your site. The server never prints the key; errors replace any `geoapi_sk_…` text with `[redacted]`, and the server writes nothing to stdout except MCP messages.
