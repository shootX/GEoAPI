# Changelog

## 0.1.0

- Read-only stdio MCP server for the GeoAPI catalog. It calls `@geoapi/sdk` and does not add an HTTP route.
- Tools: `geoapi_list_brands`, `geoapi_list_models`, `geoapi_list_generations`, `geoapi_list_years`, `geoapi_list_modifications`, `geoapi_get_modification`, `geoapi_get_specs`, `geoapi_get_equipment`, `geoapi_get_coverage`.
- The API key comes from `GEOAPI_API_KEY`. It is not a tool argument and it is not written to logs or tool results.
- Page size defaults to 20 and cannot exceed 100. 401 is returned once. 429 and 503 use the SDK retry, then the tool error includes `retryAfter`.
- This package is private to the repository. It is not on the public npm registry. A live BMW prompt was not run.
