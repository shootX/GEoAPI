<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * Validated list query for the public catalog API.
 * `limit` is the page size. `per_page` is the same value when `limit` is absent.
 */
final class ListQuery
{
    /** @var array<string, int|string> */
    public array $filters = [];

    public ?string $error = null;

    public function __construct(
        public int $page = 1,
        public int $limit = 100,
        public ?string $sort = null,
        public string $direction = 'asc',
        public ?string $q = null,
        public ?string $modifiedAfter = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $sortable
     * @param  list<string>  $allowedFilters
     */
    public static function parse(array $input, array $sortable, array $allowedFilters): self
    {
        $query = new self;
        $page = self::integer($input['page'] ?? null, 1, 1000000);
        if ($page === false) {
            $query->error = 'პარამეტრი page არასწორია. მიუთითეთ დადებითი მთელი რიცხვი, არაუმეტეს 1000000.';

            return $query;
        }
        $query->page = $page ?? 1;

        $hasLimit = array_key_exists('limit', $input) && $input['limit'] !== null && $input['limit'] !== '';
        $hasPerPage = array_key_exists('per_page', $input) && $input['per_page'] !== null && $input['per_page'] !== '';
        $size = self::integer($hasLimit ? $input['limit'] : ($hasPerPage ? $input['per_page'] : null), 1, 1000);
        if ($size === false) {
            $query->error = $hasLimit
                ? 'პარამეტრი limit არასწორია. მიუთითეთ მთელი რიცხვი 1-დან 1000-მდე.'
                : 'პარამეტრი per_page არასწორია. მიუთითეთ მთელი რიცხვი 1-დან 1000-მდე.';

            return $query;
        }
        $query->limit = $size ?? 100;

        if (array_key_exists('sort', $input) && $input['sort'] !== null && $input['sort'] !== '') {
            $sort = $input['sort'];
            if (! is_string($sort) || ! in_array($sort, $sortable, true)) {
                $query->error = 'პარამეტრი sort არასწორია.';

                return $query;
            }
            $query->sort = $sort;
        }

        if (array_key_exists('direction', $input) && $input['direction'] !== null && $input['direction'] !== '') {
            $direction = $input['direction'];
            if (! is_string($direction) || ! in_array(strtolower($direction), ['asc', 'desc'], true)) {
                $query->error = 'პარამეტრი direction არასწორია. გამოიყენეთ asc ან desc.';

                return $query;
            }
            $query->direction = strtolower($direction);
        }

        if (array_key_exists('q', $input) && $input['q'] !== null && $input['q'] !== '') {
            $q = $input['q'];
            if (! is_string($q) || mb_strlen(trim($q)) < 1 || mb_strlen(trim($q)) > 100 || preg_match('/[\x00-\x1F]/', $q) === 1) {
                $query->error = 'პარამეტრი q არასწორია.';

                return $query;
            }
            $query->q = trim($q);
        }

        if (array_key_exists('modified_after', $input) && $input['modified_after'] !== null && $input['modified_after'] !== '') {
            $modified = self::timestamp($input['modified_after']);
            if ($modified === null) {
                $query->error = 'პარამეტრი modified_after არასწორია. გამოიყენეთ UTC თარიღი, მაგალითად 2026-01-01T00:00:00Z.';

                return $query;
            }
            $query->modifiedAfter = $modified;
        }

        foreach (self::FILTERS as $name => $kind) {
            if (! array_key_exists($name, $input) || $input[$name] === null || $input[$name] === '') {
                continue;
            }
            if (! in_array($name, $allowedFilters, true)) {
                $query->error = 'ფილტრი '.$name.' ამ მისამართზე არ გამოიყენება.';

                return $query;
            }
            $value = self::filter($kind, $input[$name]);
            if ($value === null) {
                $query->error = 'პარამეტრი '.$name.' არასწორია.';

                return $query;
            }
            $query->filters[$name] = $value;
        }

        return $query;
    }

    public function fails(): bool
    {
        return $this->error !== null;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    /** @return array<string, int|string> */
    public function linkQuery(int $page): array
    {
        $params = ['page' => $page, 'limit' => $this->limit];
        if ($this->sort !== null) {
            $params['sort'] = $this->sort;
            $params['direction'] = $this->direction;
        }
        foreach ($this->filters as $name => $value) {
            $params[$name] = $value;
        }
        if ($this->q !== null) {
            $params['q'] = $this->q;
        }
        if ($this->modifiedAfter !== null) {
            $params['modified_after'] = $this->modifiedAfter;
        }

        return $params;
    }

    /**
     * @return array{data: list<mixed>, meta: array{page:int, per_page:int, total:int}, collection: array{count:int, total:int, pages:int, next:?string, prev:?string, first:?string, last:?string}}
     */
    public function envelope(string $path, array $rows, int $total): array
    {
        $pages = $total === 0 ? 0 : (int) ceil($total / $this->limit);
        $link = function (int $page) use ($path): string {
            return $path.'?'.http_build_query($this->linkQuery($page));
        };

        return [
            'data' => array_values($rows),
            'meta' => [
                'page' => $this->page,
                'per_page' => $this->limit,
                'total' => $total,
            ],
            'collection' => [
                'count' => count($rows),
                'total' => $total,
                'pages' => $pages,
                'next' => $pages > 0 && $this->page < $pages ? $link($this->page + 1) : null,
                'prev' => $pages > 0 && $this->page > 1 ? $link($this->page - 1) : null,
                'first' => $pages > 0 ? $link(1) : null,
                'last' => $pages > 0 ? $link($pages) : null,
            ],
        ];
    }

    /** @var array<string, string> */
    private const FILTERS = [
        'year_from' => 'year',
        'year_to' => 'year',
        'volume_from' => 'volume',
        'volume_to' => 'volume',
        'body' => 'text',
        'fuel' => 'text',
        'drive' => 'text',
        'transmission' => 'text',
        'brand_id' => 'id',
        'model_id' => 'id',
    ];

    private static function integer(mixed $value, int $min, int $max): int|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value) || ! is_string($value) || preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
            return false;
        }
        $number = (int) $value;
        if ($number < $min || $number > $max) {
            return false;
        }

        return $number;
    }

    private static function timestamp(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?)?$/', $value) !== 1) {
            return null;
        }
        $stamp = strtotime($value);
        if ($stamp === false) {
            return null;
        }

        return gmdate('Y-m-d\TH:i:s\Z', $stamp);
    }

    private static function filter(string $kind, mixed $value): int|string|null
    {
        if (! is_string($value)) {
            return null;
        }
        if ($kind === 'year') {
            if (preg_match('/^[1-9][0-9]{3}$/', $value) !== 1) {
                return null;
            }
            $year = (int) $value;

            return $year >= 1886 && $year <= 2100 ? $year : null;
        }
        if ($kind === 'volume') {
            if (preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
                return null;
            }
            $volume = (int) $value;

            return $volume >= 1 && $volume <= 20000 ? $volume : null;
        }
        if ($kind === 'id') {
            if (preg_match('/^[1-9][0-9]{0,18}$/', $value) !== 1) {
                return null;
            }

            return $value;
        }
        $text = trim($value);
        if ($text === '' || mb_strlen($text) > 64 || preg_match('/[\x00-\x1F]/', $text) === 1) {
            return null;
        }

        return $text;
    }
}
