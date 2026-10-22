<?php

declare(strict_types=1);

namespace Carapi\Catalog;

use App\Catalog\CatalogApi;
use Carapi\Platform\UnavailableException as PlatformUnavailable;
use Carapi\Platform\UsageLimiter;
use Carapi\Widget\Cors;
use PDOException;

final class Http
{
    public static function dispatch(string $path): void
    {
        $result = (new CatalogApi)->handle($path);
        foreach ($result['headers'] as $name => $value) {
            header($name.': '.$value);
        }
        self::json($result['status'], $result['body']);
    }

    public static function readForProject(int $projectId, string $kind, ?string $slug, ?string $id): void
    {
        try {
            $limiter = UsageLimiter::fromEnv();
            $now = UsageLimiter::now();
            $ipHit = $limiter->hitIp(UsageLimiter::clientIp(), $now);
            if ($ipHit['exceeded']) {
                self::limited('RATE_LIMIT_EXCEEDED', $ipHit['retry_after']);

                return;
            }
            $projectHit = $limiter->hitProject($projectId, $now);
            if ($projectHit['missing']) {
                self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);

                return;
            }
            if ($projectHit['exceeded']) {
                self::limited('RATE_LIMIT_EXCEEDED', max($ipHit['retry_after'], $projectHit['retry_after']), $projectHit['limit'], $projectHit['reset_at']);

                return;
            }
            $preview = $limiter->previewMonth($projectId, $now);
            $day = $limiter->previewDay($projectId, $now);
            if ($preview === null || $day === null) {
                self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);

                return;
            }
            if (! empty($preview['cap'])) {
                self::limited('PROJECT_CAP_EXCEEDED', max(1, $preview['reset_at'] - $now), $preview['limit'], $preview['reset_at']);

                return;
            }
            if ($preview['exhausted']) {
                self::limited('MONTHLY_QUOTA_EXCEEDED', max(1, $preview['reset_at'] - $now), $preview['limit'], $preview['reset_at']);

                return;
            }
            if ($day['enforced'] && $day['exhausted']) {
                self::limited('DAILY_QUOTA_EXCEEDED', max(1, $day['reset_at'] - $now), $day['limit'], $day['reset_at']);

                return;
            }
            $page = self::pageParams();
            if ($page === null) {
                return;
            }
            $repo = new CatalogRepository(Connection::pdo());
            if ($kind === 'widget:brands') {
                $result = $repo->brands($page['per_page'], $page['offset']);
            } elseif ($kind === 'widget:models') {
                if (! $repo->brandExists((string) $slug)) {
                    self::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული ბრენდი ვერ მოიძებნა.']]);

                    return;
                }
                $result = $repo->models((string) $slug, $page['per_page'], $page['offset']);
            } elseif ($kind === 'widget:years') {
                if (! $repo->modelExists((string) $id)) {
                    self::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული მოდელი ვერ მოიძებნა.']]);

                    return;
                }
                $choices = $repo->yearChoices((string) $id);
                $result = [
                    'rows' => array_slice($choices['rows'], $page['offset'], $page['per_page']),
                    'total' => $choices['total'],
                ];
            } elseif ($kind === 'widget:generations') {
                if (! $repo->modelExists((string) $id)) {
                    self::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული მოდელი ვერ მოიძებნა.']]);

                    return;
                }
                $year = self::yearParam($_GET['year'] ?? null, false);
                if ($year === false) {
                    return;
                }
                if ($year === null) {
                    $result = $repo->generations((string) $id, $page['per_page'], $page['offset']);
                } else {
                    $choices = PickerSelection::generations(Connection::pdo(), (string) $id, $year);
                    if ($choices === null) {
                        self::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული მოდელი ვერ მოიძებნა.']]);

                        return;
                    }
                    $result = [
                        'rows' => array_slice($choices['rows'], $page['offset'], $page['per_page']),
                        'total' => $choices['total'],
                    ];
                }
            } elseif ($kind === 'widget:modifications') {
                $year = self::yearParam($_GET['year'] ?? null, true);
                if ($year === false) {
                    return;
                }
                $choices = PickerSelection::modifications(Connection::pdo(), (string) $id, $year);
                if ($choices === null) {
                    self::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული თაობა ვერ მოიძებნა.']]);

                    return;
                }
                $result = [
                    'rows' => array_slice($choices['rows'], $page['offset'], $page['per_page']),
                    'total' => $choices['total'],
                ];
            } else {
                self::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული მისამართით მონაცემები ვერ მოიძებნა.']]);

                return;
            }
            self::sendCounted($limiter, ['key_id' => 0, 'project_id' => $projectId], $kind, [
                'data' => $result['rows'],
                'meta' => ['page' => $page['page'], 'per_page' => $page['per_page'], 'total' => $result['total']],
            ], $now, $projectHit);
        } catch (UnavailableException|PlatformUnavailable|PDOException) {
            self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);
        }
    }

    /**
     * @param  array{key_id:int,project_id:int}  $access
     * @param  array<string, mixed>  $payload
     * @param  array{limit:int,count:int,reset_at:int}|null  $minute
     */
    private static function sendCounted(UsageLimiter $limiter, array $access, string $route, array $payload, int $now, ?array $minute = null): void
    {
        try {
            $saved = $limiter->commitSuccess($access['project_id'], $route, $now);
            if ($access['key_id'] > 0) {
                Access::touch($access['key_id']);
            }
        } catch (UnavailableException|PlatformUnavailable|PDOException) {
            self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);

            return;
        }
        if (! $saved['ok']) {
            $code = ($saved['reason'] ?? 'month') === 'day' ? 'DAILY_QUOTA_EXCEEDED' : 'MONTHLY_QUOTA_EXCEEDED';
            self::limited($code, max(1, $saved['reset_at'] - $now), $saved['limit'], $saved['reset_at']);

            return;
        }
        header('X-Quota-Limit: '.$saved['limit']);
        header('X-Quota-Remaining: '.$saved['remaining']);
        header('X-Quota-Reset: '.$saved['reset_at']);
        if ($minute !== null) {
            header('X-RateLimit-Limit: '.$minute['limit']);
            header('X-RateLimit-Remaining: '.max(0, $minute['limit'] - $minute['count']));
            header('X-RateLimit-Reset: '.$minute['reset_at']);
        }
        self::json(200, $payload);
    }

    private static function limited(string $code, int $retryAfter, ?int $limit = null, ?int $reset = null): void
    {
        header('Retry-After: '.max(1, $retryAfter));
        if ($limit !== null && $reset !== null) {
            header('X-RateLimit-Limit: '.$limit);
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: '.$reset);
        }
        self::json(429, ['error' => ['code' => $code, 'message' => self::limitMessage($code)]]);
    }

    /** The 429 codes stay as clients already match them; the message says which limit was hit. */
    public static function limitMessage(string $code): string
    {
        return match ($code) {
            'DAILY_QUOTA_EXCEEDED' => 'დღიური ლიმიტი მიღწეულია.',
            'MONTHLY_QUOTA_EXCEEDED' => 'თვიური კვოტა ამოიწურა. ის მომდევნო თვის დასაწყისში განახლდება.',
            'PROJECT_CAP_EXCEEDED' => 'პროექტის თვიური ზღვარი მიღწეულია. ზღვარი პროექტის პარამეტრებში იცვლება.',
            default => 'წუთის მოთხოვნების ლიმიტი მიღწეულია. სცადეთ Retry-After წამის შემდეგ.',
        };
    }

    public static function enabled(): bool
    {
        $value = carapi_env('CARAPI_CATALOG_ENABLED');

        return is_string($value) && in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
    }

    /** Missing year is null. An unusable year is false and already answered with 422. */
    public static function yearParam(mixed $value, bool $required): int|false|null
    {
        if ($value === null || $value === '') {
            if ($required) {
                self::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => 'year უნდა იყოს ოთხნიშნა წელი 1880-დან 2100-მდე.']]);

                return false;
            }

            return null;
        }
        $text = is_int($value) ? (string) $value : (is_string($value) ? $value : '');
        if (preg_match('/^(18[8-9][0-9]|19[0-9]{2}|20[0-9]{2}|2100)$/', $text) !== 1) {
            self::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => 'year უნდა იყოს ოთხნიშნა წელი 1880-დან 2100-მდე.']]);

            return false;
        }

        return (int) $text;
    }

    /** @return array{page:int, per_page:int, offset:int}|null */
    private static function pageParams(): ?array
    {
        $page = self::positive($_GET['page'] ?? null, 1, 1000000, 'page');
        if ($page === null) {
            return null;
        }
        $perPage = self::positive($_GET['per_page'] ?? null, 50, 100, 'per_page');
        if ($perPage === null) {
            return null;
        }

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    private static function positive(mixed $value, int $default, int $max, string $name): ?int
    {
        if ($value === null) {
            return $default;
        }
        if (is_array($value) || ! is_string($value) || ! preg_match('/^[1-9][0-9]*$/', $value)) {
            $hint = $name === 'per_page'
                ? 'პარამეტრი per_page არასწორია. მიუთითეთ მთელი რიცხვი 1-დან 100-მდე.'
                : 'პარამეტრი page არასწორია. მიუთითეთ დადებითი მთელი რიცხვი, არაუმეტეს 1000000.';
            self::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => $hint]]);

            return null;
        }
        $number = (int) $value;
        if ($number < 1 || $number > $max) {
            $hint = $name === 'per_page'
                ? 'პარამეტრი per_page არასწორია. მიუთითეთ მთელი რიცხვი 1-დან 100-მდე.'
                : 'პარამეტრი page არასწორია. მიუთითეთ დადებითი მთელი რიცხვი, არაუმეტეს 1000000.';
            self::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => $hint]]);

            return null;
        }

        return $number;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public static function translate(array $payload): array
    {
        if (isset($payload['error']) && is_array($payload['error']) && ! self::prefersGeorgian()) {
            $message = (string) ($payload['error']['message'] ?? '');
            if ($message !== '' && preg_match('/\p{Georgian}/u', $message) === 1) {
                $payload['error']['message'] = self::englishMessage((string) ($payload['error']['code'] ?? ''), $message);
            }
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    public static function json(int $status, array $payload): void
    {
        $payload = self::translate($payload);
        Cors::apply();
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function prefersGeorgian(): bool
    {
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (! is_string($header) || trim($header) === '') {
            return true;
        }
        $first = strtolower(trim(explode(';', explode(',', $header)[0])[0]));

        return $first === '' || str_starts_with($first, 'ka');
    }

    private static function englishMessage(string $code, string $message): string
    {
        $known = [
            'მითითებული მისამართით მონაცემები ვერ მოიძებნა.' => 'Nothing was found at this address.',
            'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.' => 'The service is temporarily unavailable. Try the request again later.',
            'ეს HTTP მეთოდი დაუშვებელია. გამოიყენეთ GET.' => 'This HTTP method is not allowed. Use GET.',
            'ეს HTTP მეთოდი დაუშვებელია. გამოიყენეთ POST.' => 'This HTTP method is not allowed. Use POST.',
            'დღიური ლიმიტი მიღწეულია.' => 'The daily limit was reached.',
            'პარამეტრი limit არასწორია. მიუთითეთ მთელი რიცხვი 1-დან 1000-მდე.' => 'limit must be an integer from 1 to 1000.',
            'პარამეტრი per_page არასწორია. მიუთითეთ მთელი რიცხვი 1-დან 1000-მდე.' => 'per_page must be an integer from 1 to 1000.',
            'პარამეტრი sort არასწორია.' => 'sort is not allowed.',
            'პარამეტრი direction არასწორია. გამოიყენეთ asc ან desc.' => 'direction must be asc or desc.',
            'პარამეტრი q არასწორია.' => 'q is invalid.',
            'პარამეტრი modified_after არასწორია. გამოიყენეთ UTC თარიღი, მაგალითად 2026-01-01T00:00:00Z.' => 'modified_after must be a UTC timestamp such as 2026-01-01T00:00:00Z.',
            'მოთხოვნა არ არის დაშვებული. შეამოწმეთ საჯარო API გასაღები, დაშვებული origin და პროექტის მდგომარეობა.' => 'The request is not allowed. Check the public key, the allowed origin, and the project.',
            'მოთხოვნა არ არის დაშვებული.' => 'The request is not allowed.',
        ];
        if (isset($known[$message])) {
            return $known[$message];
        }

        return match ($code) {
            'unauthorized' => 'The API key is missing or no longer works.',
            'forbidden' => 'The request is not allowed.',
            'not_found' => 'The requested record was not found.',
            'invalid_parameter' => 'A parameter is invalid.',
            'unavailable' => 'The service is temporarily unavailable.',
            'RATE_LIMIT_EXCEEDED' => 'The per-minute request limit was reached. Retry after Retry-After seconds.',
            'DAILY_QUOTA_EXCEEDED' => 'The daily limit was reached.',
            'MONTHLY_QUOTA_EXCEEDED' => 'The monthly quota was reached. It renews at the start of next month.',
            'PROJECT_CAP_EXCEEDED' => 'The project\'s monthly cap was reached. Change it in the project settings.',
            default => 'The request could not be completed.',
        };
    }
}
