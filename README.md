# carapi

**A free, open-source alternative to imagin.studio that returns REAL car photographs — not 3D renders.**

Give it a make and a model, get back an actual photo of that car, resolved from
[Wikidata](https://www.wikidata.org) and served from [Wikimedia Commons](https://commons.wikimedia.org)
with full licence + attribution metadata. No API key, no signup, CORS-enabled.

```html
<img src="/getImage?make=Toyota&model=Camry">
```

- **Author:** [Anovo.Ge](https://anovo.ge)
- **Drop-in for imagin.studio** — accepts `make`, `modelFamily`, `modelYear`, `angle`, `customer`, … with the same `/getImage` path
- **Great coverage where render APIs fail** — post-Soviet models (Lada Niva, ZAZ, GAZ Volga, UAZ, Moskvitch), Cyrillic make names (`ВАЗ`, `ЗАЗ`) included, via multilingual Wikidata search + transliteration and a vehicle-class filter that keeps out ships, products and galaxies
- **Zero dependencies** — plain PHP 8.2+, no framework, no SDKs; Composer is optional (autoload only)
- **Polite to Wikimedia** — every image is downloaded once (as an 800px thumbnail) and cached content-addressed; negative results are cached for 7 days

## Quick start

```bash
php -S localhost:8080 -t public public/index.php
```

Then:

```bash
# The image itself (streams from local cache after first hit)
curl -L "http://localhost:8080/getImage?make=Lada&model=Niva" -o niva.jpg

# Metadata, licence and attribution
curl "http://localhost:8080/getImage?make=Lada&model=Niva&format=json"
```

`http://localhost:8080/` serves a small self-documenting landing page.

> **Set your User-Agent.** The Wikimedia [User-Agent policy](https://meta.wikimedia.org/wiki/User-Agent_policy)
> asks API clients to identify themselves with contact info. Please run your instance with:
> `CARAPI_USER_AGENT="MyProject/1.0 (https://my.site; me@my.site)"`

## Endpoint

`GET /getImage`

| Param | Required | Notes |
|---|---|---|
| `make` | yes | e.g. `Toyota`, `ВАЗ`, `ЗАЗ` — Cyrillic works |
| `model` | yes | e.g. `Camry`, `Niva`. Alias: `modelFamily` (imagin-compatible) |
| `year` | no | Narrows the Wikidata search. Alias: `modelYear` |
| `format` | no | `json` for metadata; default returns the image |
| `angle`, `customer`, `zoomType`, … | no | Accepted for imagin.studio compatibility and **ignored** — real photographs come with a fixed viewpoint, and no customer key is needed |

### Responses

**Default (image mode)** — the photo itself: streamed bytes with the local
filesystem cache, or a `302` redirect when a CDN-backed cache is configured.
Licence metadata always travels in headers:

```
X-Image-Source:      wikimedia
X-Image-License:     CC BY-SA 3.0
X-Image-Attribution: Lada Niva — © Sah68 (CC BY-SA 3.0), via Wikimedia Commons
```

**`format=json`**:

```json
{
  "found": true,
  "make": "Lada", "model": "Niva", "year": null,
  "image_url": "http://localhost:8080/getImage?make=Lada&model=Niva",
  "source": "wikimedia",
  "title": "Lada Niva",
  "license": "CC BY-SA 3.0",
  "author": "Sah68",
  "attribution": "Lada Niva — © Sah68 (CC BY-SA 3.0), via Wikimedia Commons",
  "reference": "Q1470103"
}
```

**No photo found** — JSON mode returns `{"found": false}` with HTTP 404; image
mode returns an inline SVG placeholder (with a 404 status), so plain `<img>`
tags degrade gracefully.

### Migrating from imagin.studio

```diff
-https://cdn.imagin.studio/getImage?customer=xxxx&make=toyota&modelFamily=camry&angle=23
+https://your-host/getImage?make=toyota&modelFamily=camry
```

Same path, same core params. `customer` is ignored (no key needed), `angle`/paint
params are ignored (you get a real photograph). What changes for your users: an
actual photo of the car instead of a render — and a **licence you must respect**
(see below).

## Attribution & licensing (important)

The **code** in this repository is MIT. The **photos** are not: they come from
Wikimedia Commons under their own licences — mostly Creative Commons
(CC BY / CC BY-SA) and public domain.

- Every successful response carries `author`, `license` and a ready-made,
  human-readable `attribution` string (in JSON and in the `X-Image-Attribution`
  header — preserved even on redirects).
- **If the licence is CC BY or CC BY-SA, you must display the attribution**
  wherever you show the image (a caption or a credits page is fine).
- Public-domain images (`Public domain`, `CC0`) need no credit.
- The `reference` field is the Wikidata entity id (e.g. `Q1470103`) if you want
  to link back or fetch more.

## How it works

1. `wbsearchentities` on Wikidata for `"make model"` (multilingual: Cyrillic
   queries also search `uk`/`ru` labels, plus a Latin transliteration fallback).
2. Keep the first candidate that is a manufactured vehicle — it must have a
   manufacturer (`P176`), an image (`P18`), and an instance-of (`P31`) class
   that denotes a road vehicle (known ids like *automobile model*, or classes
   whose English label matches car/truck/SUV/…).
3. Fetch the Commons `imageinfo` for the photo: 800px thumbnail URL, author,
   licence short-name.
4. Download the thumbnail **once**, store it content-addressed
   (`<md5-of-bytes>.<ext>`) in the cache with the query→photo mapping; misses
   are cached for 7 days.

## Configuration

All optional, via environment variables:

| Env var | Default | Purpose |
|---|---|---|
| `CARAPI_USER_AGENT` | `carapi/1.0 (+repo URL)` | User-Agent for Wikimedia API calls — **set this** with your project URL + contact email |
| `CARAPI_CACHE_DIR` | `./cache` | Where images + metadata are stored (must be writable by the web server) |
| `CARAPI_CACHE_URL` | *(unset)* | Public base URL under which you expose `<cache>/img/` (nginx alias, CDN, …). When set, image mode answers with a `302` to `<url>/<file>` instead of streaming bytes |

### Swapping the cache for S3-compatible storage

The cache is behind a small interface (`src/Cache/CacheInterface.php`):
`getMeta`/`setMeta` for query metadata, `hasImage`/`putImage`/`readImage`/`imageUrl`
for content-addressed blobs. The default `FilesystemCache` writes to a local
directory. To serve from S3-compatible object storage behind a CDN (what the
hosted demo does), implement the interface with your S3 client — `putImage()` →
PutObject, `imageUrl()` → your public CDN URL (which switches the endpoint into
302-redirect mode) — and swap the `new FilesystemCache(...)` line in
`public/index.php`. Details in the interface docblock.

## Deploying

### PHP built-in server (dev)

```bash
php -S 0.0.0.0:8080 -t public public/index.php
```

### Docker Compose (php:8.3-apache)

```yaml
services:
  carapi:
    image: php:8.3-apache
    ports:
      - "8080:80"
    volumes:
      - ./:/var/www/carapi
      - carapi-cache:/var/www/carapi/cache
    environment:
      CARAPI_USER_AGENT: "MyCarImages/1.0 (https://example.com; ops@example.com)"
    command: >
      bash -c "
        sed -ri 's!/var/www/html!/var/www/carapi/public!g' /etc/apache2/sites-available/000-default.conf &&
        printf '<Directory /var/www/carapi/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-enabled/carapi.conf &&
        a2enmod rewrite &&
        chown -R www-data:www-data /var/www/carapi/cache &&
        apache2-foreground"

volumes:
  carapi-cache:
```

`docker compose up`, then `http://localhost:8080/getImage?make=Toyota&model=Camry`.
The bundled `public/.htaccess` routes everything to the front controller; for
nginx use a standard `try_files $uri /index.php?$args;` location.

### Production notes

- Put a caching proxy/CDN in front — responses send `Cache-Control: public, max-age=86400`.
- The first request for a new make/model does 2–4 Wikimedia API calls plus one
  image download; everything after that is served from your cache.
- Don't strip the attribution headers at your proxy.

## Repository layout

```
public/index.php            front controller: /getImage + landing page (also a php -S router)
public/.htaccess            Apache rewrite to the front controller
src/DromResolver.php        make/model[/year] -> catalog photo used by /getImage
src/Service.php             orchestration: cache lookup -> resolve -> download once -> store
src/Cache/CacheInterface.php  storage contract (metadata + content-addressed blobs)
src/Cache/FilesystemCache.php local-directory cache (the default)
```

## License

- Code: [MIT](LICENSE) © [Anovo.Ge](https://anovo.ge)
- Photos: © their respective authors, via Wikimedia Commons, under the licence
  stated in each response — attribution required where the licence says so.
