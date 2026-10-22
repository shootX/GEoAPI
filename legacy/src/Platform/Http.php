<?php

declare(strict_types=1);

namespace Carapi\Platform;

use PDOException;

final class Http
{
    public const TERMS_VERSION = '2026-09-26';
    public static function enabled(): bool
    {
        $value = carapi_env('CARAPI_PLATFORM_ENABLED');

        return is_string($value) && in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
    }

    public static function handles(string $path): bool
    {
        return in_array($path, ['/login', '/register', '/logout', '/account', '/admin', '/auth/me', '/email/verification/resend', '/email/verify', '/forgot-password', '/reset-password'], true)
            || preg_match('#^/admin/users(?:/[^/]+)?$#', $path) === 1
            || preg_match('#^/admin/projects(?:/[^/]+)?$#', $path) === 1
            || preg_match('#^/account/projects/[1-9][0-9]*(/(preview|playground|playground/run|explorer))?$#', $path) === 1;
    }

    public static function dispatch(string $path): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        try {
            if (preg_match('#^/account/projects/([1-9][0-9]*)/preview$#', $path, $match) === 1) {
                self::preview((int) $match[1]);

                return;
            }
            if (preg_match('#^/account/projects/([1-9][0-9]*)/playground/run$#', $path, $match) === 1) {
                self::playgroundRun((int) $match[1], $method);

                return;
            }
            if (preg_match('#^/account/projects/([1-9][0-9]*)/playground$#', $path, $match) === 1) {
                self::playground((int) $match[1], $method);

                return;
            }
            if (preg_match('#^/account/projects/([1-9][0-9]*)/explorer$#', $path, $match) === 1) {
                self::explorer((int) $match[1], $method);

                return;
            }
            if (preg_match('#^/account/projects/([1-9][0-9]*)$#', $path, $match) === 1) {
                self::project($method, (int) $match[1]);

                return;
            }
            if ($path === '/admin/users' || str_starts_with($path, '/admin/users/')) {
                self::adminUsers($method, $path);

                return;
            }
            if ($path === '/admin/projects' || str_starts_with($path, '/admin/projects/')) {
                self::adminProjects($method, $path);

                return;
            }
            match ($path) {
                '/login' => self::login($method),
                '/register' => self::register($method),
                '/logout' => self::logout($method),
                '/account' => self::account($method),
                '/admin' => self::admin($method),
                '/auth/me' => self::me($method),
                '/email/verification/resend' => self::resend($method),
                '/email/verify' => self::verify($method),
                '/forgot-password' => self::forgot($method),
                '/reset-password' => self::reset($method),
                default => self::notFound(),
            };
        } catch (UnavailableException | PDOException) {
            error_log('[platform] request failed');
            if (self::wantsJson()) {
                self::json(503, ['ok' => false, 'error' => 'unavailable']);

                return;
            }
            View::unavailable();
        }
    }

    /** @return array{label:string}|null */
    public static function accountLink(): ?array
    {
        if (!self::enabled()) {
            return null;
        }
        $user = self::activeUser();

        return $user === null ? null : ['label' => trim($user['first_name'] . ' ' . $user['last_name'])];
    }

    private static function login(string $method): void
    {
        if ($method === 'GET') {
            $current = self::activeUser();
            if ($current !== null) {
                self::redirect(self::afterLogin((int) $current['id']));
            }
            View::login([], []);

            return;
        }
        if ($method !== 'POST') {
            self::methodNotAllowed();

            return;
        }
        $input = self::input();
        $token = $input['_csrf'] ?? null;
        if (!Session::checkCsrf(is_string($token) ? $token : null)) {
            self::fail(419, ['form' => 'სესია ამოიწურა. განაახლეთ გვერდი და სცადეთ ხელახლა.'], $input);

            return;
        }
        $email = UserRepository::email((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $users = new UserRepository(Connection::pdo());
        $ip = self::ip();
        if ($users->tooManyAttempts($email, $ip)) {
            self::fail(429, ['form' => 'ძალიან ბევრი მცდელობაა. სცადეთ მოგვიანებით'], $input);

            return;
        }
        $row = $users->findByEmail($email);
        $ok = $row !== null
            && (string) $row['status'] === 'active'
            && Passwords::verify($password, (string) $row['password_hash']);
        if (!$ok) {
            $users->recordFailure($email, $ip);
            self::fail(401, ['form' => 'ელფოსტა ან პაროლი არასწორია. შეამოწმეთ მონაცემები და სცადეთ ხელახლა.'], $input);

            return;
        }
        $users->touchLogin((int) $row['id']);
        Session::login((int) $row['id'], (int) $row['auth_version']);
        if (class_exists(\App\Account\IdentitySync::class)) {
            \App\Account\IdentitySync::ensure((int) $row['id']);
        }
        if (self::wantsJson()) {
            self::json(200, ['ok' => true, 'user' => self::publicUser($row)]);

            return;
        }
        self::redirect(self::afterLogin((int) $row['id']));
    }

    private static function afterLogin(int $userId): string
    {
        $role = (new UserRepository(Connection::pdo()))->role($userId);

        return $role === 'admin' ? '/admin' : '/account';
    }

    private static function register(string $method): void
    {
        $input = $method === 'POST' ? self::input() : [];
        if ($method === 'GET') {
            if (self::activeUser() !== null) {
                self::redirect('/account');
            }
            View::register([], []);

            return;
        }
        if ($method !== 'POST') {
            self::methodNotAllowed();

            return;
        }
        if (!Session::checkCsrf(is_string($input['_csrf'] ?? null) ? $input['_csrf'] : null)) {
            self::fail(419, ['form' => 'სესია ამოიწურა. განაახლეთ გვერდი და სცადეთ ხელახლა.'], $input, true);

            return;
        }
        $errors = self::validate($input);
        if ($errors !== []) {
            self::fail(422, $errors, $input, true);

            return;
        }
        $users = new UserRepository(Connection::pdo());
        $result = $users->insert([
            'first_name' => trim((string) $input['first_name']),
            'last_name' => trim((string) $input['last_name']),
            'email' => UserRepository::email((string) $input['email']),
            'password_hash' => Passwords::hash((string) $input['password']),
            'terms_version' => self::TERMS_VERSION,
        ]);
        if (!$result['ok']) {
            self::fail(422, ['email' => 'ეს ელფოსტა უკვე რეგისტრირებულია. შედით ან აღადგინეთ პაროლი.'], $input, true);

            return;
        }
        try {
            $token = (new Tokens(Connection::pdo()))->issue((int) $result['id'], 'verify_email', UserRepository::email((string) $input['email']));
            [$subject, $text] = Letters::verifyEmail(Mail::appUrl() . '/email/verify?token=' . $token);
            Mail::make()->send(UserRepository::email((string) $input['email']), $subject, $text);
        } catch (\Throwable $e) {
            error_log('[platform] verification mail failed ' . $e::class);
        }
        $created = (new UserRepository(Connection::pdo()))->findActive((int) $result['id']);
        Session::login((int) $result['id'], (int) ($created['auth_version'] ?? 1));
        if (class_exists(\App\Account\IdentitySync::class)) {
            \App\Account\IdentitySync::ensure((int) $result['id']);
        }
        if (self::wantsJson()) {
            $row = $users->findActive((int) $result['id']);
            self::json(201, ['ok' => true, 'user' => $row === null ? [] : self::publicUser($row)]);

            return;
        }
        self::redirect('/account');
    }

    private static function logout(string $method): void
    {
        if ($method !== 'POST') {
            self::methodNotAllowed();

            return;
        }
        $input = self::input();
        if (!Session::checkCsrf(is_string($input['_csrf'] ?? null) ? $input['_csrf'] : null)) {
            self::fail(419, ['form' => 'სესია ამოიწურა. განაახლეთ გვერდი და სცადეთ ხელახლა.']);

            return;
        }
        Session::logout();
        if (self::wantsJson()) {
            self::json(200, ['ok' => true]);

            return;
        }
        self::redirect('/');
    }

    private static function account(string $method): void
    {
        $user = self::activeUser();
        if ($user === null) {
            self::redirect('/login');
        }
        $projects = new ProjectRepository(Connection::pdo());
        if ($method === 'POST') {
            self::accountPost($user, $projects);

            return;
        }
        if ($method !== 'GET') {
            self::methodNotAllowed();

            return;
        }
        View::account($user, $projects->forUser((int) $user['id']), [], []);
    }

    private static function admin(string $method): void
    {
        $user = self::requireAdmin();
        if ($user === null) {
            return;
        }
        if ($method !== 'GET') {
            self::readOnly();

            return;
        }
        $overview = (new AdminOverview(Connection::pdo()))->collect(UsageLimiter::now());
        View::admin($user, $overview);
    }

    private static function adminUsers(string $method, string $path): void
    {
        $user = self::requireAdmin();
        if ($user === null) {
            return;
        }
        if ($method !== 'GET') {
            self::readOnly();

            return;
        }
        $directory = new AdminUsers(Connection::pdo());
        if ($path === '/admin/users') {
            View::adminUsers($user, $directory->search($_GET));

            return;
        }
        $id = 0;
        if (preg_match('#^/admin/users/([1-9][0-9]*)$#', $path, $match) === 1) {
            $id = (int) $match[1];
        }
        $detail = $id > 0 ? $directory->find($id, UsageLimiter::now()) : null;
        if ($detail === null) {
            self::notFound();

            return;
        }
        View::adminUser($user, $detail);
    }

    private static function adminProjects(string $method, string $path): void
    {
        $user = self::requireAdmin();
        if ($user === null) {
            return;
        }
        if ($method !== 'GET') {
            self::readOnly();

            return;
        }
        $directory = new AdminProjects(Connection::pdo());
        $now = UsageLimiter::now();
        if ($path === '/admin/projects') {
            View::adminProjects($user, $directory->search($_GET, $now));

            return;
        }
        $id = 0;
        if (preg_match('#^/admin/projects/([1-9][0-9]*)$#', $path, $match) === 1) {
            $id = (int) $match[1];
        }
        $detail = $id > 0 ? $directory->find($id, $now) : null;
        if ($detail === null) {
            self::notFound();

            return;
        }
        View::adminProject($user, $detail);
    }

    /** @return array<string, mixed>|null */
    private static function requireAdmin(): ?array
    {
        $active = self::activeUser();
        if ($active === null) {
            self::redirect('/login');
        }
        $role = (new UserRepository(Connection::pdo()))->role((int) $active['id']);
        if ($role !== 'admin') {
            self::notFound();

            return null;
        }

        return $active;
    }

    private static function readOnly(): void
    {
        http_response_code(405);
        header('Allow: GET');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, mixed> $user */
    private static function accountPost(array $user, ProjectRepository $projects): void
    {
        $input = self::input();
        if (!Session::checkCsrf(is_string($input['_csrf'] ?? null) ? $input['_csrf'] : null)) {
            self::fail(419, ['form' => 'სესია ამოიწურა. განაახლეთ გვერდი და სცადეთ ხელახლა.']);

            return;
        }
        $action = (string) ($input['action'] ?? '');
        $userId = (int) $user['id'];
        if ($action === 'create_project') {
            $parsed = self::projectInput($input);
            if ($parsed['errors'] !== []) {
                http_response_code(422);
                View::account($user, $projects->forUser($userId), $parsed['errors'], $input);

                return;
            }
            try {
                $projects->insert($userId, $parsed['name'], $parsed['website']);
            } catch (\App\Billing\V2\ProjectLimitReached $e) {
                http_response_code(422);
                View::account($user, $projects->forUser($userId), ['name' => $e->getMessage()], $input);

                return;
            }
            self::redirect('/account');
        }
        $id = (int) ($input['project_id'] ?? 0);
        if ($projects->findOwned($userId, $id) === null) {
            self::notFound();

            return;
        }
        if ($action === 'update_project') {
            $parsed = self::projectInput($input);
            if ($parsed['errors'] !== []) {
                http_response_code(422);
                View::account($user, $projects->forUser($userId), $parsed['errors'], $input);

                return;
            }
            $projects->update($userId, $id, $parsed['name'], $parsed['website']);
            self::redirect('/account');
        }
        if ($action === 'set_project_status') {
            $status = (string) ($input['status'] ?? '');
            if (!in_array($status, ['active', 'disabled'], true) || !$projects->setStatus($userId, $id, $status)) {
                self::notFound();

                return;
            }
            self::redirect('/account');
        }
        self::notFound();
    }

    private static function playground(int $id, string $method): void
    {
        if ($method !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);

            return;
        }
        $user = self::activeUser();
        if ($user === null) {
            self::redirect('/login');
        }
        $project = (new ProjectRepository(Connection::pdo()))->findOwned((int) $user['id'], $id);
        if ($project === null) {
            self::notFound();

            return;
        }
        View::playground($user, $project);
    }

    private static function explorer(int $id, string $method): void
    {
        if ($method !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);

            return;
        }
        $user = self::activeUser();
        if ($user === null) {
            self::redirect('/login');
        }
        $project = (new ProjectRepository(Connection::pdo()))->findOwned((int) $user['id'], $id);
        if ($project === null) {
            self::notFound();

            return;
        }
        View::explorer($user, $project);
    }

    private static function playgroundRun(int $id, string $method): void
    {
        if ($method !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            self::json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'ეს HTTP მეთოდი დაუშვებელია. გამოიყენეთ POST.']]);

            return;
        }
        if (!Playground::catalogEnabled()) {
            self::json(404, ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'მითითებული მისამართით მონაცემები ვერ მოიძებნა.']]);

            return;
        }
        try {
            $limiter = UsageLimiter::fromEnv();
            $now = UsageLimiter::now();
            $ipHit = $limiter->hitIp(UsageLimiter::clientIp(), $now);
        } catch (UnavailableException | \Carapi\Catalog\UnavailableException | PDOException) {
            self::playgroundUnavailable();

            return;
        }
        if ($ipHit['exceeded']) {
            self::playgroundLimited('RATE_LIMIT_EXCEEDED', $ipHit['retry_after']);

            return;
        }
        $user = self::activeUser();
        if ($user === null) {
            self::json(401, ['ok' => false, 'error' => ['code' => 'unauthorized', 'message' => 'ავტორიზაცია ვერ შესრულდა. შედით ანგარიშში და სცადეთ ხელახლა.']]);

            return;
        }
        $input = self::input();
        if (!Session::checkCsrf(is_string($input['_csrf'] ?? null) ? $input['_csrf'] : null)) {
            self::json(419, ['ok' => false, 'error' => ['code' => 'invalid_csrf', 'message' => 'სესია ამოიწურა. განაახლეთ გვერდი და სცადეთ ხელახლა.']]);

            return;
        }
        $project = (new ProjectRepository(Connection::pdo()))->findOwned((int) $user['id'], $id);
        if ($project === null) {
            self::json(404, ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'მითითებული პროექტი ვერ მოიძებნა.']]);

            return;
        }
        if ((string) $project['status'] !== 'active' || $user['email_verified_at'] === null) {
            self::json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'მოთხოვნა არ არის დაშვებული. საჭიროა დადასტურებული ელფოსტა და აქტიური პროექტი.']]);

            return;
        }
        $ready = Playground::parse($input);
        if (!$ready['ok']) {
            self::json(422, ['ok' => false, 'error' => ['code' => 'invalid_parameter', 'message' => $ready['message']]]);

            return;
        }
        if (!Playground::operationEnabled($ready['route'])) {
            self::json(404, ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'მითითებული მისამართით მონაცემები ვერ მოიძებნა.']]);

            return;
        }
        try {
            $projectHit = $limiter->hitProject($id, $now);
            if ($projectHit['missing']) {
                self::playgroundUnavailable();

                return;
            }
            if ($projectHit['exceeded']) {
                self::playgroundLimited('RATE_LIMIT_EXCEEDED', max($ipHit['retry_after'], $projectHit['retry_after']));

                return;
            }
            $preview = $limiter->previewMonth($id, $now);
            if ($preview === null) {
                self::playgroundUnavailable();

                return;
            }
            if ($preview['exhausted']) {
                self::playgroundLimited('MONTHLY_QUOTA_EXCEEDED', max(1, $preview['reset_at'] - $now));

                return;
            }
            $result = Playground::execute($ready);
            if ($result['status'] !== 200) {
                self::json($result['status'], [
                    'ok' => false,
                    'status' => $result['status'],
                    'error' => $result['body']['error'] ?? ['code' => 'not_found', 'message' => 'მითითებული მისამართით მონაცემები ვერ მოიძებნა.'],
                ]);

                return;
            }
            $saved = $limiter->commitSuccess($id, $result['route'], $now);
        } catch (UnavailableException | \Carapi\Catalog\UnavailableException | PDOException) {
            self::playgroundUnavailable();

            return;
        }
        if (!$saved['ok']) {
            self::playgroundLimited('MONTHLY_QUOTA_EXCEEDED', max(1, $saved['reset_at'] - $now));

            return;
        }
        self::json(200, [
            'ok' => true,
            'status' => 200,
            'headers' => [
                'X-Quota-Limit' => (string) $saved['limit'],
                'X-Quota-Remaining' => (string) $saved['remaining'],
                'X-Quota-Reset' => (string) $saved['reset_at'],
            ],
            'body' => $result['body'],
        ]);
    }

    private static function playgroundUnavailable(): void
    {
        self::json(503, ['ok' => false, 'error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);
    }

    private static function playgroundLimited(string $code, int $retryAfter): void
    {
        header('Retry-After: ' . max(1, $retryAfter));
        self::json(429, ['ok' => false, 'error' => ['code' => $code, 'message' => 'მოთხოვნების ლიმიტი მიღწეულია.']]);
    }

    private static function preview(int $id): void
    {
        $user = self::activeUser();
        if ($user === null) {
            self::redirect('/login');
        }
        $project = (new ProjectRepository(Connection::pdo()))->findOwned((int) $user['id'], $id);
        $widget = $project === null ? null : (new WidgetRepository(Connection::pdo()))->findOwned((int) $user['id'], $id);
        if ($project === null || $widget === null) {
            self::notFound();

            return;
        }
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        $key = htmlspecialchars((string) $widget['public_key'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><html lang="ka"><head><meta charset="utf-8"><title>ვიჯეტის წინასწარი ნახვა</title></head><body>';
        echo '<script src="/widget/v1/geoapi-filter.js"></script>';
        echo '<geoapi-filter public-key="' . $key . '"></geoapi-filter>';
        echo '<pre id="out"></pre><script' . \Carapi\Csp::attr() . '>document.querySelector("geoapi-filter").addEventListener("geoapi:change",function(e){document.getElementById("out").textContent=JSON.stringify(e.detail);});</script>';
        echo '</body></html>';
    }

    private static function project(string $method, int $id): void
    {
        $user = self::activeUser();
        if ($user === null) {
            self::redirect('/login');
        }
        $projects = new ProjectRepository(Connection::pdo());
        $project = $projects->findOwned((int) $user['id'], $id);
        if ($project === null) {
            self::notFound();

            return;
        }
        if ($method === 'POST') {
            self::projectPost($user, $projects, $project);

            return;
        }
        if ($method !== 'GET') {
            self::methodNotAllowed();

            return;
        }
        $widget = (new WidgetRepository(Connection::pdo()))->ensure((int) $user['id'], $id);
        View::project($user, $project, $projects->keys((int) $user['id'], $id), null, [], [], $widget);
    }

    /** @param array<string, mixed> $user @param array<string, mixed> $project */
    private static function projectPost(array $user, ProjectRepository $projects, array $project): void
    {
        $input = self::input();
        if (!Session::checkCsrf(is_string($input['_csrf'] ?? null) ? $input['_csrf'] : null)) {
            self::fail(419, ['form' => 'სესია ამოიწურა. განაახლეთ გვერდი და სცადეთ ხელახლა.']);

            return;
        }
        $userId = (int) $user['id'];
        $projectId = (int) $project['id'];
        $action = (string) ($input['action'] ?? '');
        $widgets = new WidgetRepository(Connection::pdo());
        if ($action === 'set_widget') {
            if ($user['email_verified_at'] === null) {
                self::notFound();

                return;
            }
            $enabled = (string) ($input['enabled'] ?? '') === '1';
            if (!$widgets->setEnabled($userId, $projectId, $enabled)) {
                self::notFound();

                return;
            }
            self::redirect('/account/projects/' . $projectId);
        }
        if ($action === 'rotate_widget') {
            if ($widgets->rotate($userId, $projectId) === null) {
                self::notFound();

                return;
            }
            self::redirect('/account/projects/' . $projectId);
        }
        if ($action === 'save_origins') {
            $lines = preg_split('/\R/u', (string) ($input['allowed_origins'] ?? '')) ?: [];
            $origins = [];
            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $normal = Origins::normalize($line);
                if ($normal === null) {
                    http_response_code(422);
                    $widget = $widgets->findOwned($userId, $projectId);
                    View::project($user, $project, $projects->keys($userId, $projectId), null, ['origins' => 'შეიყვანეთ საიტის origin, მაგალითად https://example.com. http დასაშვებია მხოლოდ localhost-ისა და 127.0.0.1-ისთვის. ბილიკი, wildcard (*) და ცარიელი მნიშვნელობა არ მიიღება.'], $input, $widget);

                    return;
                }
                $origins[] = $normal;
            }
            if (!$widgets->setOrigins($userId, $projectId, $origins)) {
                self::notFound();

                return;
            }
            self::redirect('/account/projects/' . $projectId);
        }
        if ($action === 'revoke_key') {
            if (!$projects->revoke($userId, $projectId, (int) ($input['key_id'] ?? 0))) {
                self::notFound();

                return;
            }
            self::redirect('/account/projects/' . $projectId);
        }
        if ($action !== 'create_key') {
            self::notFound();

            return;
        }
        if ($user['email_verified_at'] === null) {
            http_response_code(403);
            View::project($user, $project, $projects->keys($userId, $projectId), null, ['form' => 'საიდუმლო API გასაღების შესაქმნელად ჯერ დაადასტურეთ ელფოსტა.'], $input);

            return;
        }
        $name = trim((string) ($input['name'] ?? ''));
        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $errors['name'] = 'შეიყვანეთ გასაღების სახელი, 2-დან 80 სიმბოლომდე.';
        }
        $expires = trim((string) ($input['expires_at'] ?? ''));
        $expiresAt = null;
        if ($expires !== '') {
            $time = strtotime($expires);
            if ($time === false || $time <= time()) {
                $errors['expires_at'] = 'ვადა მომავალი თარიღი უნდა იყოს. ცარიელი ველი ნიშნავს, რომ გასაღებს ვადა არ აქვს.';
            } else {
                $expiresAt = gmdate('c', $time);
            }
        }
        if ($errors !== []) {
            http_response_code(422);
            View::project($user, $project, $projects->keys($userId, $projectId), null, $errors, $input);

            return;
        }
        if (class_exists(\App\Access\LegacyGate::class)) {
            $denial = \App\Access\LegacyGate::issueKey($userId);
            if ($denial !== null) {
                http_response_code($denial['status']);
                View::project($user, $project, $projects->keys($userId, $projectId), null, ['form' => $denial['body']['error']['message']], $input);

                return;
            }
        }
        $issued = ApiKeys::issue();
        if (!$projects->insertKey($userId, $projectId, $name, $issued['selector'], $issued['hash'], $expiresAt)) {
            self::notFound();

            return;
        }
        header('Cache-Control: no-store');
        View::project($user, $project, $projects->keys($userId, $projectId), $issued['token'], [], []);
    }

    /** @param array<string, mixed> $input @return array{name:string,website:?string,errors:array<string,string>} */
    private static function projectInput(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $websiteRaw = trim((string) ($input['website_url'] ?? ''));
        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors['name'] = 'შეიყვანეთ პროექტის სახელი, 2-დან 120 სიმბოლომდე.';
        }
        $website = null;
        if ($websiteRaw !== '') {
            $parts = parse_url($websiteRaw);
            $scheme = is_array($parts) ? ($parts['scheme'] ?? '') : '';
            if (!is_array($parts) || !in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass']) || empty($parts['host'])) {
                $errors['website_url'] = 'შეიყვანეთ საიტის მისამართი http ან https პროტოკოლით, მომხმარებლის სახელის გარეშე.';
            } else {
                $website = $websiteRaw;
            }
        }

        return ['name' => $name, 'website' => $website, 'errors' => $errors];
    }

    private static function me(string $method): void
    {
        if ($method !== 'GET') {
            self::methodNotAllowed();

            return;
        }
        $user = self::activeUser();
        if ($user === null) {
            self::json(401, ['ok' => false, 'error' => 'unauthorized']);

            return;
        }
        self::json(200, ['ok' => true, 'user' => self::publicUser($user)]);
    }

    private static function resend(string $method): void
    {
        if ($method !== 'POST') {
            self::methodNotAllowed();

            return;
        }
        $user = self::activeUser();
        if ($user === null) {
            self::redirect('/login');
        }
        $input = self::input();
        if (!Session::checkCsrf(is_string($input['_csrf'] ?? null) ? $input['_csrf'] : null)) {
            self::fail(419, ['form' => 'სესია ამოიწურა. განაახლეთ გვერდი და სცადეთ ხელახლა.']);

            return;
        }
        $wait = (new Tokens(Connection::pdo()))->limited((string) $user['email'], self::ip(), 'verify_email');
        if ($wait !== null) {
            http_response_code(429);
            View::account($user, (new ProjectRepository(Connection::pdo()))->forUser((int) $user['id']), ['form' => 'დადასტურების წერილის ხელახლა გაგზავნა შესაძლებელია ' . $wait . ' წამში.'], []);

            return;
        }
        try {
            $token = (new Tokens(Connection::pdo()))->issue((int) $user['id'], 'verify_email', (string) $user['email']);
            [$subject, $text] = Letters::verifyEmail(Mail::appUrl() . '/email/verify?token=' . $token);
            Mail::make()->send((string) $user['email'], $subject, $text);
        } catch (UnavailableException) {
            error_log('[platform] verification mail failed');
            View::account($user, (new ProjectRepository(Connection::pdo()))->forUser((int) $user['id']), ['form' => 'დადასტურების წერილი ვერ გაიგზავნა. სცადეთ მოგვიანებით.'], []);

            return;
        }
        self::redirect('/account');
    }

    private static function verify(string $method): void
    {
        $token = (string) ($_GET['token'] ?? ($_POST['token'] ?? ''));
        self::tokenHeaders();
        if ($method === 'GET') {
            View::confirm('ელფოსტის დადასტურება', '/email/verify', $token, 'დადასტურება', []);

            return;
        }
        if ($method !== 'POST') {
            self::methodNotAllowed();

            return;
        }
        $input = self::input();
        if (!Session::checkCsrf(is_string($input['_csrf'] ?? null) ? $input['_csrf'] : null)) {
            View::confirm('ელფოსტის დადასტურება', '/email/verify', $token, 'დადასტურება', ['form' => 'სესია ამოიწურა. განაახლეთ გვერდი და სცადეთ ხელახლა.']);

            return;
        }
        if (!(new Tokens(Connection::pdo()))->guessesLeft(self::ip())) {
            View::confirm('ელფოსტის დადასტურება', '/email/verify', '', 'დადასტურება', ['form' => 'ბმული არასწორია ან მისი ვადა ამოიწურა. მოითხოვეთ ახალი ბმული.']);

            return;
        }
        $result = (new Tokens(Connection::pdo()))->verifyEmail((string) ($input['token'] ?? ''));
        if (!$result['ok']) {
            View::confirm('ელფოსტის დადასტურება', '/email/verify', '', 'დადასტურება', ['form' => 'ბმული არასწორია ან მისი ვადა ამოიწურა. მოითხოვეთ ახალი ბმული.']);

            return;
        }
        if (isset($result['user_id']) && class_exists(\App\Account\IdentitySync::class)) {
            \App\Account\IdentitySync::markLaravelVerified((int) $result['user_id']);
        }
        View::notice('ელფოსტა დადასტურდა', 'ელფოსტა დადასტურდა. შედით ანგარიშში. API გასაღები ავტომატურად არ იქმნება — პროექტს კაბინეტში დაამატებთ.');
    }

    private static function forgot(string $method): void
    {
        if ($method === 'GET') {
            View::forgot([]);

            return;
        }
        if ($method !== 'POST') {
            self::methodNotAllowed();

            return;
        }
        $input = self::input();
        $message = 'თუ მითითებული ელფოსტით ანგარიში რეგისტრირებულია, პაროლის აღდგენის ინსტრუქცია გაიგზავნება.';
        if (!Session::checkCsrf(is_string($input['_csrf'] ?? null) ? $input['_csrf'] : null)) {
            View::forgot(['form' => 'სესია ამოიწურა. განაახლეთ გვერდი და სცადეთ ხელახლა.']);

            return;
        }
        $email = UserRepository::email((string) ($input['email'] ?? ''));
        $users = new UserRepository(Connection::pdo());
        $tokens = new Tokens(Connection::pdo());
        $row = $users->findByEmail($email);
        hash('sha256', random_bytes(32));
        if ($row !== null && $tokens->limited($email, self::ip(), 'reset_password') === null) {
            try {
                $token = $tokens->issue((int) $row['id'], 'reset_password', (string) $row['email']);
                [$subject, $text] = Letters::resetPassword(Mail::appUrl() . '/reset-password?token=' . $token);
                Mail::make()->send((string) $row['email'], $subject, $text);
            } catch (UnavailableException) {
                error_log('[platform] reset mail failed');
            }
        }
        View::forgot(['form' => $message], true);
    }

    private static function reset(string $method): void
    {
        $token = (string) ($_GET['token'] ?? ($_POST['token'] ?? ''));
        self::tokenHeaders();
        if ($method === 'GET') {
            View::resetForm($token, []);

            return;
        }
        if ($method !== 'POST') {
            self::methodNotAllowed();

            return;
        }
        $input = self::input();
        if (!Session::checkCsrf(is_string($input['_csrf'] ?? null) ? $input['_csrf'] : null)) {
            View::resetForm($token, ['form' => 'სესია ამოიწურა. განაახლეთ გვერდი და სცადეთ ხელახლა.']);

            return;
        }
        $password = (string) ($input['password'] ?? '');
        $error = Passwords::error($password);
        if ($error !== null) {
            View::resetForm($token, ['password' => $error]);

            return;
        }
        if ($password !== (string) ($input['password_confirmation'] ?? '')) {
            View::resetForm($token, ['password_confirmation' => 'შეყვანილი პაროლები ერთმანეთს არ ემთხვევა.']);

            return;
        }
        if (!(new Tokens(Connection::pdo()))->guessesLeft(self::ip())) {
            View::resetForm('', ['form' => 'ბმული არასწორია ან მისი ვადა ამოიწურა. მოითხოვეთ ახალი ბმული.']);

            return;
        }
        $result = (new Tokens(Connection::pdo()))->resetPassword((string) ($input['token'] ?? ''), Passwords::hash($password));
        if (!$result['ok']) {
            View::resetForm('', ['form' => 'ბმული არასწორია ან მისი ვადა ამოიწურა. მოითხოვეთ ახალი ბმული.']);

            return;
        }
        try {
            $sentTo = self::emailForResetToken((string) ($input['token'] ?? ''));
            if ($sentTo !== null) {
                [$subject, $text] = Letters::passwordChanged();
                Mail::make()->send($sentTo, $subject, $text);
            }
        } catch (UnavailableException) {
            error_log('[platform] reset notice failed');
        }
        View::notice('პაროლი შეიცვალა', 'პაროლი შეიცვალა. შედით ახალი პაროლით.');
    }

    private static function emailForResetToken(string $token): ?string
    {
        $stmt = Connection::pdo()->prepare(
            "SELECT u.email FROM platform.email_tokens t JOIN platform.users u ON u.id = t.user_id WHERE t.token_hash = :hash AND t.purpose = 'reset_password' AND t.used_at IS NOT NULL ORDER BY t.used_at DESC LIMIT 1"
        );
        $stmt->execute(['hash' => hash('sha256', $token)]);
        $email = $stmt->fetchColumn();

        return is_string($email) ? $email : null;
    }

    private static function tokenHeaders(): void
    {
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    private static function validate(array $input): array
    {
        $errors = [];
        $first = trim((string) ($input['first_name'] ?? ''));
        $last = trim((string) ($input['last_name'] ?? ''));
        $email = UserRepository::email((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $confirm = (string) ($input['password_confirmation'] ?? '');
        if (!preg_match('/^[\p{L}\s\-\']{2,80}$/u', $first)) {
            $errors['first_name'] = 'შეიყვანეთ სახელი. დასაშვებია ასოები, შორისები, დეფისი და აპოსტროფი, 2-დან 80 სიმბოლომდე.';
        }
        if (!preg_match('/^[\p{L}\s\-\']{2,80}$/u', $last)) {
            $errors['last_name'] = 'შეიყვანეთ გვარი. დასაშვებია ასოები, შორისები, დეფისი და აპოსტროფი, 2-დან 80 სიმბოლომდე.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'შეიყვანეთ ელფოსტის სწორი მისამართი.';
        }
        $passwordError = Passwords::error($password);
        if ($passwordError !== null) {
            $errors['password'] = $passwordError;
        } elseif ($password !== $confirm) {
            $errors['password_confirmation'] = 'შეყვანილი პაროლები ერთმანეთს არ ემთხვევა.';
        }
        if (($input['accept_terms'] ?? '') !== '1') {
            $errors['accept_terms'] = 'გამოყენების წესებზე დათანხმება სავალდებულოა. მონიშნეთ ველი და სცადეთ ხელახლა.';
        }

        return $errors;
    }

    /** @return array<string, mixed>|null */
    private static function activeUser(): ?array
    {
        $id = Session::id();
        if ($id === null) {
            return null;
        }
        $user = (new UserRepository(Connection::pdo()))->findActive($id);
        if ($user === null || Session::authVersion() !== (int) $user['auth_version']) {
            Session::logout();

            return null;
        }

        return $user;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function publicUser(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'first_name' => (string) $row['first_name'],
            'last_name' => (string) $row['last_name'],
            'email' => (string) $row['email'],
        ];
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private static function fail(int $status, array $errors, array $old = [], bool $register = false): void
    {
        if (self::wantsJson()) {
            self::json($status, ['ok' => false, 'errors' => $errors]);

            return;
        }
        http_response_code($status);
        if ($register) {
            View::register($errors, $old);

            return;
        }
        View::login($errors, $old);
    }

    /** @return array<string, mixed> */
    private static function input(): array
    {
        $type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($type, 'application/json')) {
            $decoded = json_decode(file_get_contents('php://input') ?: '', true);

            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    private static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $type = $_SERVER['CONTENT_TYPE'] ?? '';

        return str_contains($type, 'application/json') || str_contains($accept, 'application/json');
    }

    private static function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        return is_string($ip) && $ip !== '' ? $ip : '0.0.0.0';
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
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
    }

    private static function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE);
    }
}
