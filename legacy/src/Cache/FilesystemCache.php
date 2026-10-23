<?php

namespace Geoapi\Cache;

/**
 * @author Anovo.Ge https://anovo.ge
 *
 * Default cache: plain local directory (no external services).
 *
 *   <dir>/meta/<sha1-of-query-key>.json   resolved metadata + negative results
 *   <dir>/img/<md5-of-bytes>.<ext>        content-addressed image blobs
 *
 * The directory defaults to ./cache in the project root (see public/index.php,
 * GEOAPI_CACHE_DIR env) and must be writable by the web server user.
 *
 * If the img/ subdirectory is additionally exposed over HTTP (e.g. an nginx alias
 * or a CDN pulling from it), pass its public base URL as $publicBaseUrl — the
 * endpoint will then 302-redirect to "<publicBaseUrl>/<file>" instead of
 * streaming the bytes through PHP (GEOAPI_CACHE_URL env).
 */
final class FilesystemCache implements CacheInterface
{
    private string $dir;
    private ?string $publicBaseUrl;

    public function __construct(string $dir, ?string $publicBaseUrl = null)
    {
        $this->dir = rtrim($dir, '/');
        $this->publicBaseUrl = $publicBaseUrl !== null && $publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : null;
        foreach ([$this->dir, $this->dir . '/meta', $this->dir . '/img'] as $d) {
            if (!is_dir($d) && !@mkdir($d, 0775, true) && !is_dir($d)) {
                throw new \RuntimeException("GeoAPI cache directory is not creatable: $d");
            }
        }
        if (!is_writable($this->dir . '/img')) {
            throw new \RuntimeException("GeoAPI cache directory is not writable: {$this->dir}");
        }
    }

    public function getMeta(string $key): ?array
    {
        $raw = @file_get_contents($this->metaPath($key));
        if ($raw === false) return null;
        $meta = json_decode($raw, true);
        return is_array($meta) ? $meta : null;
    }

    public function setMeta(string $key, array $meta): void
    {
        $meta['stored_at'] = time();
        $this->atomicWrite($this->metaPath($key), json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function hasImage(string $file): bool
    {
        return is_file($this->imgPath($file));
    }

    public function putImage(string $file, string $bytes, string $mime): void
    {
        $this->atomicWrite($this->imgPath($file), $bytes);
    }

    public function readImage(string $file): ?string
    {
        $raw = @file_get_contents($this->imgPath($file));
        return $raw === false ? null : $raw;
    }

    public function imageUrl(string $file): ?string
    {
        return $this->publicBaseUrl !== null ? $this->publicBaseUrl . '/' . $file : null;
    }

    private function metaPath(string $key): string
    {
        return $this->dir . '/meta/' . sha1($key) . '.json';
    }

    private function imgPath(string $file): string
    {
        // $file is "<md5>.<ext>" produced by Geoapi\Service — never user input —
        // but sanitise anyway so a custom caller can't traverse paths.
        return $this->dir . '/img/' . basename($file);
    }

    /** Write via tmp file + rename so concurrent readers never see a partial file. */
    private function atomicWrite(string $path, string $data): void
    {
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $data) === false) {
            throw new \RuntimeException("GeoAPI cache write failed: $path");
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException("GeoAPI cache rename failed: $path");
        }
    }
}
