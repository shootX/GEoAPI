<?php

namespace Carapi\Cache;

/**
 * @author Anovo.Ge https://anovo.ge
 *
 * Storage backend for resolved images + their metadata, so the API serves from
 * cache instead of hammering Wikimedia on every request.
 *
 * Two kinds of records:
 *  - metadata, keyed by the normalised query ("make|model|year"):
 *    ['status' => 1, 'file' => '<md5>.jpg', 'mime' => …, 'source' => …, 'title' => …,
 *     'license' => …, 'author' => …, 'attribution' => …, 'src_ref' => …, 'stored_at' => ts]
 *    or a negative result ['status' => 0, 'stored_at' => ts] (re-checked after a TTL);
 *  - image blobs, content-addressed by "<md5-of-bytes>.<ext>" (identical photos
 *    reached via different queries are stored once).
 *
 * The default implementation is {@see FilesystemCache} (local ./cache directory).
 *
 * To back the cache with S3-compatible object storage (AWS S3, MinIO, …) instead,
 * implement this interface with your S3 client:
 *  - putImage()  -> PutObject under e.g. "carphotos/<file>" with the given mime;
 *  - hasImage()  -> HeadObject (or return true optimistically after a putImage);
 *  - imageUrl()  -> your public bucket/CDN base URL + "/carphotos/<file>" — when it
 *                   returns a URL the endpoint issues a 302 redirect instead of
 *                   streaming bytes, and readImage() is never called;
 *  - get/setMeta -> a small table/KV of your choice, or reuse FilesystemCache for
 *                   metadata and S3 only for blobs.
 * Then swap the `new FilesystemCache(...)` line in public/index.php.
 */
interface CacheInterface
{
    /** Stored metadata for a query key, or null if never seen. */
    public function getMeta(string $key): ?array;

    /** Persist metadata for a query key. Implementations should add 'stored_at' => time(). */
    public function setMeta(string $key, array $meta): void;

    /** Whether the content-addressed image blob exists. */
    public function hasImage(string $file): bool;

    /** Store an image blob under its content-addressed name. */
    public function putImage(string $file, string $bytes, string $mime): void;

    /** Raw image bytes for streaming, or null if missing. Unused when imageUrl() returns a URL. */
    public function readImage(string $file): ?string;

    /**
     * Public URL of the cached image (CDN-backed caches), or null when the blob
     * has no URL of its own and must be streamed by the endpoint.
     */
    public function imageUrl(string $file): ?string;
}
