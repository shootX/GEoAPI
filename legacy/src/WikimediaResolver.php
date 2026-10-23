<?php

namespace GeoAPI;

/**
 * Resolve a car make/model[/year] to a REAL photograph from Wikimedia Commons
 * (via Wikidata), with attribution + licence — the free, legal alternative to
 * studio 3D renders (imagin.studio etc.).
 *
 * Dependency-light on purpose (plain HTTP via file_get_contents, no SDK/DB).
 * The caching/serving layer lives in Geoapi\Service + Geoapi\Cache\*.
 *
 * Flow: Wikidata wbsearchentities("make model") -> keep the first candidate that is a
 * manufactured vehicle (has manufacturer P176) with an image (P18) -> Commons imageinfo
 * for the file URL + author + licence.
 *
 * Coverage is excellent for mainstream AND post-Soviet models that render services lack
 * (Lada Niva/Samara, ZAZ Sens, Daewoo Lanos, GAZ Volga, UAZ, Moskvitch, …).
 *
 * @author Anovo.Ge https://anovo.ge
 */
final class WikimediaResolver
{
    /**
     * Wikimedia API etiquette requires a descriptive User-Agent with contact info.
     * Override via the GEOAPI_USER_AGENT env var with YOUR project URL + email, e.g.
     *   GEOAPI_USER_AGENT="MyCarImages/1.0 (https://example.com; ops@example.com)"
     * See https://meta.wikimedia.org/wiki/User-Agent_policy
     */
    private const DEFAULT_UA = 'GeoAPI/1.0 (https://geoapi.ge)';

    private const WD      = 'https://www.wikidata.org/w/api.php';
    private const COMMONS = 'https://commons.wikimedia.org/w/api.php';
    private const TIMEOUT = 12;

    /** The User-Agent sent to Wikimedia APIs (GEOAPI_USER_AGENT env or a default). */
    public static function userAgent(): string
    {
        $ua = geoapi_env('GEOAPI_USER_AGENT');
        return is_string($ua) && trim($ua) !== '' ? trim($ua) : self::DEFAULT_UA;
    }

    /**
     * @return array{source:string,wikidata:string,title:string,file:string,
     *               source_url:string,thumb_url:string,license:string,author:string,attribution:string}|null
     */
    public static function resolve(string $make, string $model, ?int $year = null): ?array
    {
        $make = trim($make);
        $model = trim($model);
        if ($make === '' && $model === '') return null;

        $entity = self::findCarEntity($make, $model, $year);
        if (!$entity) return null;

        $info = self::commonsInfo($entity['file']);
        if (!$info || empty($info['url'])) return null;

        return [
            'source'      => 'wikimedia',
            'wikidata'    => $entity['qid'],
            'title'       => $entity['label'],
            'file'        => $entity['file'],
            'source_url'  => $info['url'],
            'thumb_url'   => $info['thumb'] !== '' ? $info['thumb'] : $info['url'],
            'license'     => $info['license'],
            'author'      => $info['author'],
            'attribution' => self::attributionLine($entity['label'], $info['author'], $info['license']),
        ];
    }

    /** Find a Wikidata car-model entity (manufacturer + image) for the query. */
    private static function findCarEntity(string $make, string $model, ?int $year): ?array
    {
        $hasCyr = (bool) preg_match('/\p{Cyrillic}/u', "$make $model");
        $queries = array_values(array_unique(array_filter([
            trim("$make $model"),
            $year ? trim("$make $model $year") : null,
            $hasCyr ? trim(self::translit($make) . ' ' . self::translit($model)) : null,
            $model !== '' ? $model : null,
        ])));
        // Cyrillic makes (ВАЗ, ЗАЗ, ГАЗ…) only match uk/ru Wikidata labels; Latin match en.
        $langs = $hasCyr ? ['uk', 'ru', 'en'] : ['en'];

        foreach ($queries as $q) {
            foreach ($langs as $lang) {
                $s = self::api(self::WD, [
                    'action' => 'wbsearchentities', 'search' => $q, 'language' => $lang,
                    'uselang' => $lang, 'type' => 'item', 'format' => 'json', 'limit' => 7,
                ]);
                if (empty($s['search'])) continue;

                $qids = array_column($s['search'], 'id');
                $e = self::api(self::WD, [
                    'action' => 'wbgetentities', 'ids' => implode('|', $qids),
                    'props' => 'claims|labels', 'languages' => 'en', 'format' => 'json',
                ]);
                if (empty($e['entities'])) continue;

                // Candidates with an image + a manufacturer; remember their P31 classes.
                $cands = []; $classIds = [];
                foreach ($qids as $qid) {
                    $cl  = $e['entities'][$qid]['claims'] ?? [];
                    $p18 = $cl['P18'][0]['mainsnak']['datavalue']['value'] ?? null;
                    if (!$p18 || empty($cl['P176'])) continue;
                    $p31 = [];
                    foreach ($cl['P31'] ?? [] as $c) {
                        $id = $c['mainsnak']['datavalue']['value']['id'] ?? null;
                        if ($id) { $p31[] = $id; $classIds[$id] = true; }
                    }
                    $cands[] = [
                        'qid' => $qid, 'file' => $p18, 'p31' => $p31,
                        'label' => $e['entities'][$qid]['labels']['en']['value'] ?? trim("$make $model"),
                    ];
                }
                if (!$cands) continue;

                // Keep only road vehicles (excludes ships/galaxies/products the loose
                // manufacturer heuristic would otherwise match).
                $vehicle = self::vehicleClasses(array_keys($classIds));
                foreach ($cands as $c) {
                    if (array_intersect($c['p31'], $vehicle)) {
                        return ['qid' => $c['qid'], 'file' => $c['file'], 'label' => $c['label']];
                    }
                }
            }
        }
        return null;
    }

    /** Of the given Wikidata class ids, those denoting a road vehicle (by known id or label). */
    private static function vehicleClasses(array $ids): array
    {
        static $known = ['Q3231690' => 1, 'Q59773381' => 1, 'Q946596' => 1]; // car model / series / off-road
        $out = []; $need = [];
        foreach ($ids as $id) { isset($known[$id]) ? $out[$id] = true : $need[] = $id; }
        if ($need) {
            $e = self::api(self::WD, [
                'action' => 'wbgetentities', 'ids' => implode('|', $need),
                'props' => 'labels', 'languages' => 'en', 'format' => 'json',
            ]);
            foreach ($need as $id) {
                $lbl = strtolower($e['entities'][$id]['labels']['en']['value'] ?? '');
                if ($lbl !== '' && preg_match('/\b(car|automobile|vehicle|truck|suv|van|coupe|sedan|hatchback|roadster|pickup|off-road|crossover|minivan|limousine)\b/', $lbl)) {
                    $out[$id] = true;
                }
            }
        }
        return array_keys($out);
    }

    /** Rough Cyrillic->Latin transliteration for a Latin search fallback. */
    private static function translit(string $s): string
    {
        $m = ['А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'E','Ж'=>'Zh','З'=>'Z','И'=>'I','Й'=>'I','І'=>'I','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'H','Ц'=>'Ts','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Sch','Ы'=>'Y','Э'=>'E','Ю'=>'Yu','Я'=>'Ya','Ь'=>'','Ъ'=>'','Є'=>'Ye','Ї'=>'Yi','Ґ'=>'G'];
        $up = mb_strtoupper($s, 'UTF-8'); $o = ''; $len = mb_strlen($up, 'UTF-8');
        for ($i = 0; $i < $len; $i++) { $c = mb_substr($up, $i, 1, 'UTF-8'); $o .= $m[$c] ?? $c; }
        return $o;
    }

    /** Commons file URL + author + licence (for attribution). */
    private static function commonsInfo(string $file): ?array
    {
        $r = self::api(self::COMMONS, [
            'action' => 'query', 'titles' => 'File:' . $file,
            'prop' => 'imageinfo', 'iiprop' => 'url|extmetadata',
            'iiurlwidth' => 800, // scaled thumbnail — Commons originals can be many MB
            'format' => 'json',
        ]);
        $pages = $r['query']['pages'] ?? [];
        if (!$pages) return null;
        $page = reset($pages);
        $ii = $page['imageinfo'][0] ?? null;
        if (!$ii) return null;

        $ex = $ii['extmetadata'] ?? [];
        return [
            'url'     => $ii['url'] ?? '',
            'thumb'   => $ii['thumburl'] ?? '',
            'author'  => trim(strip_tags($ex['Artist']['value'] ?? '')),
            'license' => $ex['LicenseShortName']['value'] ?? ($ex['License']['value'] ?? ''),
        ];
    }

    private static function attributionLine(string $title, string $author, string $license): string
    {
        $author  = $author !== '' ? $author : 'Unknown author';
        $license = $license !== '' ? " ($license)" : '';
        return "$title — © $author$license, via Wikimedia Commons";
    }

    private static function api(string $base, array $params): ?array
    {
        $ctx = stream_context_create(['http' => [
            'header'  => 'User-Agent: ' . self::userAgent() . "\r\n",
            'timeout' => self::TIMEOUT,
        ]]);
        $raw = @file_get_contents($base . '?' . http_build_query($params), false, $ctx);
        return $raw ? json_decode($raw, true) : null;
    }
}
