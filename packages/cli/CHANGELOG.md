# Changelog

## 0.1.0

- Private CLI for the GeoAPI catalog. It calls `@geoapi/sdk` and does not keep a second HTTP client.
- `GEOAPI_API_KEY` is the only place for the key. `--api-key` is refused and the value is not printed. stdout is the JSON document. The API code goes to stderr.
- Exit 0 is success, 2 is invalid input, 3 is auth, 4 is rate limited, and 5 is network or service. 429 and 503 use the SDK retry, at most twice.
- This package is private to the repository. It is not published on the public npm registry. A live catalog call was not run.
