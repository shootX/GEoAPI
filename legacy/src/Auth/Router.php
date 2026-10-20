<?php

declare(strict_types=1);

namespace Carapi\Auth;

use Carapi\Cache\FilesystemCache;
use Carapi\Service;

final class Router
{
    public static function dispatch(string $path): bool
    {
        $routes = ['/login', '/register', '/logout', '/account', '/auth/me'];
        if (!in_array($path, $routes, true)) {
            return false;
        }

        SessionAuth::start();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        return match ($path) {
            '/login' => self::login($method),
            '/register' => self::register($method),
            '/logout' => self::logout($method),
            '/account' => self::account($method),
            '/auth/me' => self::me($method),
            default => false,
        };
    }

    private static function login(string $method): bool
    {
        if ($method === 'GET') {
            if (SessionAuth::user() !== null) {
                self::redirect('/account');
            }
            View::login([], []);

            return true;
        }
        if ($method !== 'POST') {
            self::methodNotAllowed();

            return true;
        }

        $input = self::input();
        if (!self::csrfOk($input)) {
            self::fail(419, ['form' => 'სესია ამოიწურა, თავიდან სცადეთ']);

            return true;
        }
        if (SessionAuth::tooManyAttempts()) {
            self::fail(429, ['form' => 'ძალიან ბევრი მცდელობაა. სცადეთ მოგვიანებით']);

            return true;
        }

        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $row = (new UserRepository(Database::pdo()))->findByEmail($email);
        if ($row === null || !password_verify($password, (string) $row['password_hash'])) {
            SessionAuth::recordFailure();
            self::fail(401, ['form' => 'ელფოსტა ან პაროლი არასწორია'], $input);

            return true;
        }

        SessionAuth::login($row);
        if (self::wantsJson()) {
            self::json(200, ['ok' => true, 'user' => SessionAuth::publicUser($row)]);

            return true;
        }
        self::redirect('/account');

        return true;
    }

    private static function register(string $method): bool
    {
        $input = $method === 'POST' ? self::input() : [];
        $type = (string) ($input['type'] ?? ($_GET['type'] ?? 'individual'));
        if (!in_array($type, ['individual', 'company'], true)) {
            $type = 'individual';
        }

        if ($method === 'GET') {
            if (SessionAuth::user() !== null) {
                self::redirect('/account');
            }
            View::register($type, [], []);

            return true;
        }
        if ($method !== 'POST') {
            self::methodNotAllowed();

            return true;
        }
        if (!self::csrfOk($input)) {
            self::fail(419, ['form' => 'სესია ამოიწურა, თავიდან სცადეთ'], $input, $type);

            return true;
        }

        $input['type'] = $type;
        $result = (new Registrar(new UserRepository(Database::pdo())))->register($input);
        if (!$result['ok']) {
            self::fail(422, $result['errors'], $input, $type);

            return true;
        }

        $row = (new UserRepository(Database::pdo()))->findById((int) $result['id']);
        if ($row === null) {
            self::fail(500, ['form' => 'რეგისტრაცია ვერ დასრულდა'], $input, $type);

            return true;
        }
        SessionAuth::login($row);
        if (self::wantsJson()) {
            self::json(201, ['ok' => true, 'user' => SessionAuth::publicUser($row)]);

            return true;
        }
        self::redirect('/account');

        return true;
    }

    private static function logout(string $method): bool
    {
        if ($method !== 'POST') {
            self::methodNotAllowed();

            return true;
        }
        $input = self::input();
        if (!self::csrfOk($input)) {
            self::fail(419, ['form' => 'სესია ამოიწურა, თავიდან სცადეთ']);

            return true;
        }
        SessionAuth::logout();
        if (self::wantsJson()) {
            self::json(200, ['ok' => true]);

            return true;
        }
        self::redirect('/');

        return true;
    }

    private static function account(string $method): bool
    {
        $user = SessionAuth::user();
        if ($user === null) {
            self::redirect('/login');
        }
        $lookups = new LookupRepository(Database::pdo());
        if ($method === 'POST') {
            return self::accountPost($user, $lookups);
        }
        if ($method !== 'GET') {
            self::methodNotAllowed();

            return true;
        }
        self::showPanel($user, $lookups, self::tab(), [], []);

        return true;
    }

    /** @param array<string, mixed> $user */
    private static function accountPost(array $user, LookupRepository $lookups): bool
    {
        $input = self::input();
        $action = (string) ($input['action'] ?? '');
        $tab = $action === 'lookup' ? 'search' : 'profile';
        if (!self::csrfOk($input)) {
            self::showPanel($user, $lookups, $tab, ['form' => 'სესია ამოიწურა, თავიდან სცადეთ'], $input, 419);

            return true;
        }

        if ($action === 'lookup') {
            $result = self::saveLookup((int) $user['id'], $input, $lookups);
            if (isset($result['id'])) {
                self::redirect('/account?id=' . $result['id']);
            }
            self::showPanel($user, $lookups, 'search', $result['errors'], $input, 422);

            return true;
        }

        $users = new UserRepository(Database::pdo());
        $errors = $action === 'phone'
            ? self::changePhone((int) $user['id'], $input, $users)
            : ($action === 'password' ? self::changePassword((int) $user['id'], $input, $users) : ['form' => 'უცნობი მოქმედება']);
        if ($errors !== []) {
            self::showPanel($user, $lookups, 'profile', $errors, $input, 422);

            return true;
        }
        SessionAuth::setFlash($action === 'phone' ? 'ტელეფონი განახლდა' : 'პაროლი განახლდა');
        self::redirect('/account?tab=profile');

        return true;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id?:int, errors:array<string, string>}
     */
    private static function saveLookup(int $userId, array $input, LookupRepository $lookups): array
    {
        $make = trim((string) ($input['make'] ?? ''));
        $model = trim((string) ($input['model'] ?? ''));
        $yearRaw = trim((string) ($input['year'] ?? ''));
        $errors = [];
        if ($make === '' || mb_strlen($make) > 80) {
            $errors['make'] = 'მარკა სავალდებულოა';
        }
        if ($model === '' || mb_strlen($model) > 80) {
            $errors['model'] = 'მოდელი სავალდებულოა';
        }
        $year = null;
        if ($yearRaw !== '') {
            $year = (int) $yearRaw;
            if (!preg_match('/^[0-9]{4}$/', $yearRaw) || $year < 1900 || $year > (int) date('Y') + 1) {
                $errors['year'] = 'წელი არასწორია';
            }
        }
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        try {
            $cache = new FilesystemCache(
                carapi_env('CARAPI_CACHE_DIR') ?: dirname(__DIR__, 2) . '/cache',
                carapi_env('CARAPI_CACHE_URL') ?: null
            );
            $photo = (new Service($cache))->get($make, $model, $year);
        } catch (\Throwable $e) {
            error_log('[carapi] panel lookup failed: ' . $e->getMessage());

            return ['errors' => ['form' => 'ფოტო ვერ მოიძებნა, სცადეთ თავიდან']];
        }

        $id = $lookups->add([
            'user_id' => $userId,
            'make' => $make,
            'model' => $model,
            'year' => $year,
            'found' => $photo !== null,
            'title' => (string) ($photo['title'] ?? ''),
            'period' => (string) ($photo['period'] ?? ''),
            'generation' => (string) ($photo['generation'] ?? ''),
            'attribution' => (string) ($photo['attribution'] ?? ''),
        ]);

        return ['id' => $id, 'errors' => []];
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    private static function changePhone(int $userId, array $input, UserRepository $users): array
    {
        $phone = preg_replace('/[\s\-()]/', '', trim((string) ($input['phone'] ?? ''))) ?? '';
        if (!preg_match('/^\+?[0-9]{9,15}$/', $phone)) {
            return ['phone' => 'ტელეფონი შეიყვანეთ ციფრებით, მაგალითად 555123456'];
        }
        $users->updatePhone($userId, $phone);

        return [];
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    private static function changePassword(int $userId, array $input, UserRepository $users): array
    {
        $row = $users->findById($userId);
        $current = (string) ($input['current_password'] ?? '');
        $password = (string) ($input['password'] ?? '');
        $confirm = (string) ($input['password_confirmation'] ?? '');
        if ($row === null || !password_verify($current, (string) $row['password_hash'])) {
            return ['current_password' => 'მიმდინარე პაროლი არასწორია'];
        }
        if (mb_strlen($password) < 8) {
            return ['password' => 'პაროლი უნდა იყოს მინიმუმ 8 სიმბოლო'];
        }
        if ($password !== $confirm) {
            return ['password_confirmation' => 'პაროლები არ ემთხვევა'];
        }
        $users->updatePassword($userId, password_hash($password, PASSWORD_DEFAULT));

        return [];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private static function showPanel(
        array $user,
        LookupRepository $lookups,
        string $tab,
        array $errors,
        array $old,
        int $status = 200
    ): void {
        if ($status !== 200) {
            http_response_code($status);
        }
        $id = (int) ($_GET['id'] ?? 0);
        $current = $id > 0 ? $lookups->find((int) $user['id'], $id) : null;
        View::panel(
            $user,
            $tab,
            $lookups->recent((int) $user['id'], $tab === 'history' ? 50 : 5),
            $current,
            $errors,
            $old,
            SessionAuth::pullFlash()
        );
    }

    private static function tab(): string
    {
        $tab = (string) ($_GET['tab'] ?? 'search');

        return in_array($tab, ['search', 'history', 'integration', 'profile'], true) ? $tab : 'search';
    }

    private static function me(string $method): bool
    {
        if ($method !== 'GET') {
            self::methodNotAllowed();

            return true;
        }
        $user = SessionAuth::user();
        if ($user === null) {
            self::json(401, ['ok' => false, 'error' => 'unauthorized']);

            return true;
        }
        self::json(200, ['ok' => true, 'user' => $user]);

        return true;
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private static function fail(int $status, array $errors, array $old = [], string $type = 'individual'): void
    {
        if (self::wantsJson()) {
            self::json($status, ['ok' => false, 'errors' => $errors]);

            return;
        }
        http_response_code($status);
        $path = strtolower(rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/'));
        if ($path === '/register') {
            View::register($type, $errors, $old);

            return;
        }
        if ($path === '/login') {
            View::login($errors, $old);

            return;
        }
        View::login($errors, []);
    }

    /** @return array<string, mixed> */
    private static function input(): array
    {
        $type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (str_contains($type, 'application/json')) {
            $decoded = json_decode(file_get_contents('php://input') ?: '', true);

            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    /** @param array<string, mixed> $input */
    private static function csrfOk(array $input): bool
    {
        $token = $input['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

        return SessionAuth::checkCsrf(is_string($token) ? $token : null);
    }

    private static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

        return str_contains($type, 'application/json')
            || str_contains($accept, 'application/json');
    }

    private static function redirect(string $to): never
    {
        header('Location: ' . $to, true, 302);
        exit;
    }

    /** @param array<string, mixed> $payload */
    private static function json(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function methodNotAllowed(): void
    {
        http_response_code(405);
        header('Allow: GET, POST');
        header('Content-Type: text/plain; charset=utf-8');
        echo "Method not allowed\n";
    }
}
