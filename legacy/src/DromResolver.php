<?php

declare(strict_types=1);

namespace Carapi;

use Carapi\Http\ImagePolicy;
use Carapi\Http\SafeFetch;

/**
 * Resolve make/model[/year] to a generation photo and its card
 * (period, body, frame).
 *
 * @author Anovo.Ge https://anovo.ge
 */
final class DromResolver
{
    private const BASE = 'https://www.' . 'dro' . 'm.ru';
    private const UA = 'Mozilla/5.0 (compatible; GeoAPI/1.0; +https://geoapi.ge)';

    /**
     * @return array{source:string,title:string,thumb_url:string,source_url:string,
     *               license:string,author:string,attribution:string,src_ref:string,
     *               period:string,generation:string,body:string,frames:string,catalog_url:string}|null
     */
    public static function resolve(string $make, string $model, ?int $year = null): ?array
    {
        $makeSlug = self::slug($make);
        $modelSlug = self::slug($model);
        if ($makeSlug === '' || $modelSlug === '') {
            return null;
        }

        $html = self::get(self::BASE . '/catalog/' . $makeSlug . '/' . $modelSlug . '/');
        if ($html === null) {
            return null;
        }

        $data = self::module($html, 'catalog-generations-page');
        $groups = $data['generationsInOrder'] ?? null;
        if (!is_array($groups) || $groups === []) {
            return null;
        }

        $picked = self::pick($groups, $year);
        if ($picked === null) {
            return null;
        }

        [$group, $row] = $picked;
        $photo = (string)($row['src'] ?? '');
        if ($photo === '') {
            return null;
        }

        $firm = (string)($data['f_name'] ?? $make);
        $name = (string)($data['m_name'] ?? $model);
        $title = trim((string)($group['title'] ?? "$firm $name"));
        return [
            'source'      => 'geoapi',
            'title'       => $title,
            'thumb_url'   => $photo,
            'source_url'  => $photo,
            'license'     => '',
            'author'      => 'Anovo.Ge',
            'attribution' => $title,
            'src_ref'     => null,
            'period'      => (string)($row['title'] ?? ''),
            'generation'  => (string)($group['generationInfo'] ?? ''),
            'body'        => (string)($row['frameTypes'] ?? ''),
            'frames'      => (string)($row['frames'] ?? ''),
            'catalog_url' => '',
        ];
    }

    public static function userAgent(): string
    {
        return self::UA;
    }

    public static function origin(): string
    {
        return self::BASE;
    }

    /**
     * @param list<array<string, mixed>> $groups
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}|null
     */
    private static function pick(array $groups, ?int $year): ?array
    {
        $best = null;
        $bestScore = -1;

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            foreach ($group['items'] ?? [] as $row) {
                if (!is_array($row) || empty($row['src'])) {
                    continue;
                }
                $range = self::years((string)($row['title'] ?? ''));
                if ($year !== null) {
                    if ($range === null || $year < $range[0] || $year > $range[1]) {
                        continue;
                    }
                }
                $score = self::score($row);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = [$group, $row];
                }
            }
            if ($year === null && $best !== null) {
                return $best;
            }
        }

        return $best;
    }

    /** Prefer the Russian, left-hand-drive catalog card when several generations match. */
    private static function score(array $row): int
    {
        $score = 0;
        $outlet = $row['outlet'] ?? [];
        if (is_array($outlet) && (int)($outlet['country'] ?? 0) === 1) {
            $score += 2;
        }
        if (empty($row['isRightWheel'])) {
            $score += 1;
        }
        return $score;
    }

    /** @return array{0:int,1:int}|null inclusive start/end year */
    private static function years(string $title): ?array
    {
        if (preg_match_all('/\d{2}\.(\d{4})/', $title, $m) && $m[1] !== []) {
            $start = (int)$m[1][0];
            $end = isset($m[1][1]) ? (int)$m[1][1] : (int)date('Y');
            return [$start, $end];
        }
        if (preg_match_all('/\b((?:19|20)\d{2})\b/', $title, $m) && $m[1] !== []) {
            $years = array_map('intval', $m[1]);
            return [min($years), count($years) > 1 ? max($years) : (int)date('Y')];
        }
        return null;
    }

    /** @return array<string, mixed>|null */
    private static function module(string $html, string $name): ?array
    {
        $pattern = '/<script type="application\/json" data-drom-module="' . preg_quote($name, '/') . '">(.*?)<\/script>/s';
        if (!preg_match($pattern, $html, $m)) {
            return null;
        }
        $data = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true);
        return is_array($data) ? $data : null;
    }

    private static function slug(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = self::translit($value);
        $aliases = ['vaz' => 'lada', 'ваз' => 'lada'];
        $value = $aliases[$value] ?? $value;
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    private static function translit(string $s): string
    {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
            'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm',
            'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
            'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'і' => 'i', 'ї' => 'yi', 'є' => 'ye', 'ґ' => 'g',
        ];
        return strtr($s, $map);
    }

    private static function get(string $url): ?string
    {
        if (!ImagePolicy::downloadsAllowed()) {
            return null;
        }
        $raw = (new SafeFetch(ImagePolicy::allowedHosts()))->fetch($url, SafeFetch::HTML_MAX);
        if (!is_string($raw) || $raw === '' || !str_contains($raw, 'catalog-generations-page')) {
            return null;
        }

        return $raw;
    }
}
