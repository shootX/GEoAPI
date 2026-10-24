<?php

namespace GeoAPI;

use Geoapi\Cache\CacheInterface;
use Geoapi\Http\ImagePolicy;
use Geoapi\Http\SafeFetch;

/**
 * Orchestration behind /getImage. Cached files stay on disk.
 * Public delivery and new downloads stay off until {@see ImagePolicy} allows them.
 *
 * @author Anovo.Ge https://anovo.ge
 */
final class Service
{
    /** Seconds before a "not found" is re-checked (7 days). */
    private const NEG_TTL = 604800;

    public function __construct(private CacheInterface $cache)
    {
    }

    /**
     * @return array{file:string,mime:string,image_url:?string,source:string,title:string,
     *               license:string,author:string,attribution:string,src_ref:?string,
     *               period:string,generation:string,body:string,frames:string,catalog_url:string}|null
     */
    public function get(string $make, string $model, ?int $year): ?array
    {
        $key = self::key($make, $model, $year);

        $meta = $this->cache->getMeta($key);
        if (is_array($meta) && ($meta['source'] ?? '') === 'geoapi' && empty($meta['catalog_url'])) {
            $status = (int)($meta['status'] ?? -1);
            if ($status === 1 && !empty($meta['file']) && $this->cache->hasImage((string) $meta['file'])
                && ImagePolicy::mayRedistribute((string) ($meta['license'] ?? ''))) {
                return $this->hit($meta);
            }
            if ($status === 0 && (time() - (int)($meta['stored_at'] ?? 0)) < self::NEG_TTL) {
                return null;
            }
        }

        if (!ImagePolicy::downloadsAllowed()) {
            return null;
        }

        $r = DromResolver::resolve($make, $model, $year);
        if (!$r) { $this->cache->setMeta($key, ['status' => 0, 'source' => 'geoapi']); return null; }

        $bytes = $this->download($r['thumb_url'] ?? $r['source_url']);
        if ($bytes === null) { $this->cache->setMeta($key, ['status' => 0, 'source' => 'geoapi']); return null; }

        $ext  = self::extFromUrl($r['thumb_url'] ?? $r['source_url']);
        $file = md5($bytes) . '.' . $ext;
        if (!$this->cache->hasImage($file)) {
            $this->cache->putImage($file, $bytes, self::mime($ext));
        }

        $meta = [
            'status'      => 1,
            'file'        => $file,
            'mime'        => self::mime($ext),
            'source'      => $r['source'],
            'title'       => $r['title'],
            'license'     => $r['license'],
            'author'      => $r['author'],
            'attribution' => $r['attribution'],
            'src_ref'     => $r['src_ref'] ?? null,
            'period'      => $r['period'] ?? '',
            'generation'  => $r['generation'] ?? '',
            'body'        => $r['body'] ?? '',
            'frames'      => $r['frames'] ?? '',
            'catalog_url' => $r['catalog_url'] ?? '',
        ];
        $this->cache->setMeta($key, $meta);
        return $this->hit($meta);
    }

    /** Normalised cache key: make|model|year, lowercased. */
    public static function key(string $make, string $model, ?int $year): string
    {
        $norm = mb_strtolower(trim($make) . '|' . trim($model) . '|' . ($year ?: ''), 'UTF-8');
        $norm = preg_replace('/\s+/', ' ', $norm);
        return mb_substr($norm, 0, 96, 'UTF-8');
    }

    private function hit(array $meta): array
    {
        return [
            'file'        => $meta['file'],
            'mime'        => $meta['mime'] ?? 'image/jpeg',
            'image_url'   => $this->cache->imageUrl($meta['file']),
            'source'      => $meta['source'] ?? '',
            'title'       => $meta['title'] ?? '',
            'license'     => $meta['license'] ?? '',
            'author'      => $meta['author'] ?? '',
            'attribution' => $meta['attribution'] ?? '',
            'src_ref'     => $meta['src_ref'] ?? null,
            'period'      => $meta['period'] ?? '',
            'generation'  => $meta['generation'] ?? '',
            'body'        => $meta['body'] ?? '',
            'frames'      => $meta['frames'] ?? '',
            'catalog_url' => $meta['catalog_url'] ?? '',
        ];
    }

    private function download(string $url): ?string
    {
        if (!ImagePolicy::downloadsAllowed()) {
            return null;
        }
        $data = (new SafeFetch(ImagePolicy::allowedHosts()))->fetch($url, SafeFetch::IMAGE_MAX);

        return (is_string($data) && strlen($data) > 1000) ? $data : null;
    }

    private static function extFromUrl(string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? ($ext === 'jpeg' ? 'jpg' : $ext) : 'jpg';
    }

    private static function mime(string $ext): string
    {
        return ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'][$ext] ?? 'image/jpeg';
    }
}
