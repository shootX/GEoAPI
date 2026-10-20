<?php

declare(strict_types=1);

namespace Carapi\Auth;

final class View
{
    /** @param array<string, string> $errors @param array<string, mixed> $old */
    public static function login(array $errors, array $old): void
    {
        $csrf = self::e(SessionAuth::csrf());
        $email = self::e((string) ($old['email'] ?? ''));
        $formError = isset($errors['form']) ? '<p class="err">' . self::e($errors['form']) . '</p>' : '';
        $body = <<<HTML
        <section class="panel">
          <h1>შესვლა</h1>
          {$formError}
          <form method="post" action="/login" autocomplete="on">
            <input type="hidden" name="_csrf" value="{$csrf}">
            <label>ელფოსტა
              <input type="email" name="email" value="{$email}" required autocomplete="username">
            </label>
            <label>პაროლი
              <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button class="btn" type="submit">შესვლა</button>
          </form>
          <p class="muted">ანგარიში არ გაქვთ? <a href="/register">რეგისტრაცია</a></p>
        </section>
        HTML;
        self::page('შესვლა', $body, $errors);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    public static function register(string $type, array $errors, array $old): void
    {
        $csrf = self::e(SessionAuth::csrf());
        $formError = isset($errors['form']) ? '<p class="err">' . self::e($errors['form']) . '</p>' : '';
        $ind = $type === 'individual' ? ' class="on"' : '';
        $co = $type === 'company' ? ' class="on"' : '';
        $fields = $type === 'company' ? self::companyFields($old, $errors) : self::individualFields($old, $errors);
        $common = self::commonFields($old, $errors);

        $body = <<<HTML
        <section class="panel">
          <h1>რეგისტრაცია</h1>
          <div class="tabs">
            <a{$ind} href="/register?type=individual">ფიზიკური პირი</a>
            <a{$co} href="/register?type=company">იურიდიული პირი</a>
          </div>
          {$formError}
          <form method="post" action="/register?type={$type}" autocomplete="on">
            <input type="hidden" name="_csrf" value="{$csrf}">
            <input type="hidden" name="type" value="{$type}">
            {$fields}
            {$common}
            <button class="btn" type="submit">რეგისტრაცია</button>
          </form>
          <p class="muted">უკვე გაქვთ ანგარიში? <a href="/login">შესვლა</a></p>
        </section>
        HTML;
        self::page('რეგისტრაცია', $body, $errors);
    }

    /**
     * @param array<string, mixed> $user
     * @param list<array<string, mixed>> $history
     * @param array<string, mixed>|null $lookup
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    public static function panel(
        array $user,
        string $tab,
        array $history,
        ?array $lookup,
        array $errors,
        array $old,
        string $flash
    ): void {
        $main = match ($tab) {
            'history' => self::historyTab($history),
            'profile' => self::profileTab($user, $errors, $old),
            'integration' => self::integrationTab($history[0] ?? $lookup),
            default => self::searchTab($history, $lookup, $errors, $old),
        };
        $note = $flash !== '' ? '<p class="ok">' . self::e($flash) . '</p>' : '';
        $formError = isset($errors['form']) ? '<p class="err">' . self::e($errors['form']) . '</p>' : '';
        $body = '<div class="desk">' . self::side($tab)
            . '<section class="panel"><h1>პანელი</h1>' . $note . $formError . $main . '</section></div>';
        self::page('პანელი', $body, $errors, true);
    }

    /** @param list<array<string, mixed>> $history @param array<string, mixed>|null $lookup @param array<string, string> $errors @param array<string, mixed> $old */
    private static function searchTab(array $history, ?array $lookup, array $errors, array $old): string
    {
        $csrf = self::e(SessionAuth::csrf());
        $make = self::e((string) ($old['make'] ?? $lookup['make'] ?? ''));
        $model = self::e((string) ($old['model'] ?? $lookup['model'] ?? ''));
        $year = self::e((string) ($old['year'] ?? $lookup['year'] ?? ''));
        $makeErr = isset($errors['make']) ? '<small class="err">' . self::e($errors['make']) . '</small>' : '';
        $modelErr = isset($errors['model']) ? '<small class="err">' . self::e($errors['model']) . '</small>' : '';
        $yearErr = isset($errors['year']) ? '<small class="err">' . self::e($errors['year']) . '</small>' : '';

        $result = '';
        if ($lookup !== null && $old === []) {
            $query = http_build_query(array_filter([
                'make' => (string) $lookup['make'],
                'model' => (string) $lookup['model'],
                'year' => $lookup['year'] !== null && $lookup['year'] !== '' ? (string) $lookup['year'] : null,
            ], static fn ($value): bool => $value !== null && $value !== ''));
            $src = '/getImage?' . $query;
            if ((int) $lookup['found'] === 1) {
                $meta = self::row('სათაური', (string) $lookup['title'])
                    . self::row('თაობა', (string) $lookup['generation'])
                    . self::row('პერიოდი', (string) $lookup['period'])
                    . self::row('წყარო', (string) $lookup['attribution']);
                $result = '<img class="shot" src="' . self::e($src) . '" alt="' . self::e((string) $lookup['title']) . '">'
                    . '<table class="kv">' . $meta . '</table>'
                    . '<p class="muted">ჩასმა</p><pre>' . self::e('<img src="' . $src . '">') . '</pre>';
            } else {
                $result = '<p class="err">ამ მარკასა და მოდელზე ფოტო ვერ მოიძებნა.</p>';
            }
        }

        $recent = $history === [] ? '' : '<h2>ბოლო მოთხოვნები</h2>' . self::historyTable($history);

        return <<<HTML
        <h2>ფოტოს ძებნა</h2>
        <form method="post" action="/account">
          <input type="hidden" name="_csrf" value="{$csrf}">
          <input type="hidden" name="action" value="lookup">
          <div class="grid">
            <label>მარკა
              <input name="make" value="{$make}" required autocomplete="off">
              {$makeErr}
            </label>
            <label>მოდელი
              <input name="model" value="{$model}" required autocomplete="off">
              {$modelErr}
            </label>
            <label>წელი
              <input name="year" value="{$year}" inputmode="numeric" maxlength="4" autocomplete="off">
              {$yearErr}
            </label>
          </div>
          <button class="btn" type="submit">ძებნა</button>
        </form>
        {$result}
        {$recent}
        HTML;
    }

    /** @param list<array<string, mixed>> $history */
    private static function historyTab(array $history): string
    {
        if ($history === []) {
            return '<h2>ისტორია</h2><p class="muted">ჯერ მოთხოვნა არ გაქვთ.</p>';
        }

        return '<h2>ისტორია</h2>' . self::historyTable($history);
    }

    /** @param list<array<string, mixed>> $history */
    private static function historyTable(array $history): string
    {
        $rows = '';
        foreach ($history as $row) {
            $when = self::localTime((string) $row['created_at']);
            $year = $row['year'] !== null && $row['year'] !== '' ? (string) $row['year'] : '—';
            $state = (int) $row['found'] === 1 ? 'მოიძებნა' : 'ვერ მოიძებნა';
            $rows .= '<tr><td><a href="/account?id=' . (int) $row['id'] . '">'
                . self::e((string) $row['make'] . ' ' . (string) $row['model']) . '</a></td><td>'
                . self::e($year) . '</td><td>' . self::e($state) . '</td><td>' . self::e($when) . '</td></tr>';
        }

        return '<table><tr><th>ავტომობილი</th><th>წელი</th><th>შედეგი</th><th>დრო</th></tr>' . $rows . '</table>';
    }

    /** @param array<string, mixed> $user @param array<string, string> $errors @param array<string, mixed> $old */
    private static function profileTab(array $user, array $errors, array $old): string
    {
        $csrf = self::e(SessionAuth::csrf());
        $phone = self::e((string) ($old['phone'] ?? $user['phone']));
        $phoneErr = isset($errors['phone']) ? '<small class="err">' . self::e($errors['phone']) . '</small>' : '';
        $currentErr = isset($errors['current_password']) ? '<small class="err">' . self::e($errors['current_password']) . '</small>' : '';
        $passErr = isset($errors['password']) ? '<small class="err">' . self::e($errors['password']) . '</small>' : '';
        $confirmErr = isset($errors['password_confirmation']) ? '<small class="err">' . self::e($errors['password_confirmation']) . '</small>' : '';
        $identity = $user['type'] === 'company'
            ? self::row('კომპანია', (string) $user['company_name'])
                . self::row('საიდენტიფიკაციო კოდი', (string) $user['identification_code'])
                . self::row('წარმომადგენელი', (string) $user['representative_name'])
            : self::row('სახელი', (string) $user['first_name'])
                . self::row('გვარი', (string) $user['last_name'])
                . self::row('პირადი ნომერი', (string) $user['personal_number']);

        return '<h2>პროფილი</h2><table class="kv">'
            . self::row('ტიპი', (string) $user['type_label'])
            . $identity
            . self::row('ელფოსტა', (string) $user['email'])
            . '</table>'
            . '<form method="post" action="/account?tab=profile"><input type="hidden" name="_csrf" value="' . $csrf . '">'
            . '<input type="hidden" name="action" value="phone"><label>ტელეფონი'
            . '<input name="phone" value="' . $phone . '" required autocomplete="tel"></label>'
            . $phoneErr . '<button class="btn" type="submit">შენახვა</button></form>'
            . '<h2>პაროლი</h2><form method="post" action="/account?tab=profile">'
            . '<input type="hidden" name="_csrf" value="' . $csrf . '"><input type="hidden" name="action" value="password">'
            . '<label>მიმდინარე პაროლი<input type="password" name="current_password" required autocomplete="current-password"></label>'
            . $currentErr
            . '<label>ახალი პაროლი<input type="password" name="password" required autocomplete="new-password"></label>'
            . $passErr
            . '<label>გამეორება<input type="password" name="password_confirmation" required autocomplete="new-password"></label>'
            . $confirmErr
            . '<button class="btn" type="submit">შეცვლა</button></form>';
    }

    /** @param array<string, mixed>|null $sample */
    private static function integrationTab(?array $sample): string
    {
        $make = self::clip((string) ($_GET['make'] ?? $sample['make'] ?? 'Toyota'));
        $model = self::clip((string) ($_GET['model'] ?? $sample['model'] ?? 'Camry'));
        $year = trim((string) ($_GET['year'] ?? $sample['year'] ?? ''));
        if (!preg_match('/^[0-9]{4}$/', $year)) {
            $year = '';
        }
        $query = http_build_query(array_filter([
            'make' => $make,
            'model' => $model,
            'year' => $year,
        ], static fn (string $value): bool => $value !== ''));
        $image = self::baseUrl() . '/getImage?' . $query;
        $json = $image . '&format=json';
        $html = '<img src="' . $image . '" alt="' . trim($make . ' ' . $model) . '">';
        $curl = 'curl "' . $json . '"';

        return '<h2>ინტეგრაცია</h2>'
            . '<p class="muted">სურათი ჩაჯდება საიტზე ან აპში ამ მისამართით.</p>'
            . '<form method="get" action="/account">'
            . '<input type="hidden" name="tab" value="integration">'
            . '<div class="grid">'
            . '<label>მარკა<input name="make" value="' . self::e($make) . '" required></label>'
            . '<label>მოდელი<input name="model" value="' . self::e($model) . '" required></label>'
            . '<label>წელი<input name="year" value="' . self::e($year) . '" inputmode="numeric" maxlength="4"></label>'
            . '</div><button class="btn" type="submit">კოდის განახლება</button></form>'
            . '<h2>სურათი</h2><pre>' . self::e($html) . '</pre>'
            . '<h2>JSON</h2><pre>' . self::e($curl) . '</pre>'
            . '<h2>პარამეტრები</h2><table class="kv">'
            . self::row('make', 'სავალდებულო. მაგალითად Toyota')
            . self::row('model', 'სავალდებულო. ალტერნატივა: modelFamily')
            . self::row('year', 'არასავალდებულო. ალტერნატივა: modelYear')
            . self::row('format', 'json — მეტამონაცემები. ცარიელი მნიშვნელობა აბრუნებს სურათს')
            . '</table>'
            . '<h2>პასუხი</h2>'
            . '<p class="muted">სურათის რეჟიმში მოდის თავად ფაილი და სათაურები X-Image-Source, X-Image-License, X-Image-Attribution.</p>'
            . '<pre>' . self::e(self::sampleJson($make, $model, $year, $image)) . '</pre>';
    }

    private static function sampleJson(string $make, string $model, string $year, string $image): string
    {
        $payload = [
            'found' => true,
            'make' => $make,
            'model' => $model,
            'year' => $year === '' ? null : (int) $year,
            'image_url' => $image,
            'source' => 'carapi',
            'title' => trim($make . ' ' . $model),
            'period' => '',
            'generation' => '',
            'body' => '',
            'frames' => '',
        ];

        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function baseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return ($https ? 'https' : 'http') . '://' . $host;
    }

    private static function clip(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return mb_substr($value, 0, 80);
    }

    private static function side(string $tab): string
    {
        $items = [
            'search' => 'ძებნა',
            'history' => 'ისტორია',
            'integration' => 'ინტეგრაცია',
            'profile' => 'პროფილი',
        ];
        $links = '';
        foreach ($items as $key => $label) {
            $class = $key === $tab ? ' class="on"' : '';
            $href = $key === 'search' ? '/account' : '/account?tab=' . $key;
            $links .= '<a' . $class . ' href="' . $href . '">' . $label . '</a>';
        }

        return '<aside class="side">' . $links . '</aside>';
    }

    private static function localTime(string $iso): string
    {
        try {
            return (new \DateTimeImmutable($iso))
                ->setTimezone(new \DateTimeZone('Asia/Tbilisi'))
                ->format('d.m.Y H:i');
        } catch (\Exception) {
            return $iso;
        }
    }

    /** @param array<string, string> $errors */
    private static function page(string $title, string $body, array $errors, bool $wide = false): void
    {
        if (isset($errors['form']) && !str_contains($body, 'class="err"')) {
            $body = '<p class="err">' . self::e($errors['form']) . '</p>' . $body;
        }
        $user = SessionAuth::user();
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo '<!doctype html><html lang="ka"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . self::e($title) . ' — carapi</title>';
        echo '<link rel="stylesheet" href="/assets/fonts/fonts.css">';
        echo self::css();
        echo '</head><body>';
        echo self::header($user);
        echo '<div class="wrap' . ($wide ? ' wide' : '') . '">' . $body . '</div>';
        echo \Carapi\SiteFooter::html();
        echo '</body></html>';
    }

    /** @param array<string, mixed>|null $user */
    private static function header(?array $user): string
    {
        $right = $user === null
            ? '<a href="/login">შესვლა</a><a class="btn" href="/register">რეგისტრაცია</a>'
            : '<a href="/account">' . self::e((string) $user['label']) . '</a>'
                . '<form method="post" action="/logout"><input type="hidden" name="_csrf" value="'
                . self::e(SessionAuth::csrf()) . '"><button class="btn" type="submit">გასვლა</button></form>';

        return '<header><div class="bar"><a class="logo" href="/"><img src="/logo.png?v=3" alt="" width="48" height="48">car<span>api</span></a>'
            . '<nav>' . $right . '</nav></div></header>';
    }

    /** @param array<string, mixed> $old @param array<string, string> $errors */
    private static function individualFields(array $old, array $errors): string
    {
        return self::field('სახელი', 'first_name', 'text', $old, $errors, 'given-name')
            . self::field('გვარი', 'last_name', 'text', $old, $errors, 'family-name')
            . self::field('პირადი ნომერი', 'personal_number', 'text', $old, $errors, 'off', '11 ციფრი', '11', '[0-9]{11}');
    }

    /** @param array<string, mixed> $old @param array<string, string> $errors */
    private static function companyFields(array $old, array $errors): string
    {
        return self::field('კომპანიის სახელწოდება', 'company_name', 'text', $old, $errors, 'organization')
            . self::field('საიდენტიფიკაციო კოდი', 'identification_code', 'text', $old, $errors, 'off', '9 ციფრი', '9', '[0-9]{9}')
            . self::field('წარმომადგენელი', 'representative_name', 'text', $old, $errors, 'name');
    }

    /** @param array<string, mixed> $old @param array<string, string> $errors */
    private static function commonFields(array $old, array $errors): string
    {
        return self::field('ელფოსტა', 'email', 'email', $old, $errors, 'email')
            . self::field('ტელეფონი', 'phone', 'tel', $old, $errors, 'tel')
            . self::field('პაროლი', 'password', 'password', $old, $errors, 'new-password', 'მინიმუმ 8 სიმბოლო')
            . self::field('პაროლის გამეორება', 'password_confirmation', 'password', $old, $errors, 'new-password');
    }

    /** @param array<string, mixed> $old @param array<string, string> $errors */
    private static function field(
        string $label,
        string $name,
        string $type,
        array $old,
        array $errors,
        string $autocomplete,
        string $hint = '',
        string $maxlength = '',
        string $pattern = ''
    ): string {
        $value = $type === 'password' ? '' : self::e((string) ($old[$name] ?? ''));
        $err = isset($errors[$name]) ? '<small class="err">' . self::e($errors[$name]) . '</small>' : '';
        $hintHtml = $hint !== '' ? '<small>' . self::e($hint) . '</small>' : '';
        $extra = '';
        if ($maxlength !== '') {
            $extra .= ' maxlength="' . self::e($maxlength) . '" inputmode="numeric"';
        }
        if ($pattern !== '') {
            $extra .= ' pattern="' . self::e($pattern) . '"';
        }

        return '<label>' . self::e($label)
            . '<input type="' . $type . '" name="' . $name . '" value="' . $value . '" required autocomplete="'
            . self::e($autocomplete) . '"' . $extra . '>'
            . $hintHtml . $err . '</label>';
    }

    private static function row(string $label, string $value): string
    {
        return '<tr><th>' . self::e($label) . '</th><td>' . self::e($value) . '</td></tr>';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function css(): string
    {
        return <<<'CSS'
<style>
  :root{--bg:#f9fafa;--ink:#141719;--muted:#5c6163;--navy:#193f68;--navy-h:#4f81b7;--line:#e1e4e5;--card:#fff}
  *{box-sizing:border-box}
  body{margin:0;font:16px/1.6 "Noto Sans Georgian",Roboto,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:var(--bg);color:var(--ink)}
  a{color:var(--navy);text-decoration:none}
  a:hover{color:var(--navy-h)}
  header{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid var(--line)}
  .bar{max-width:1120px;margin:0 auto;padding:16px 22px;display:flex;align-items:center;gap:18px}
  .logo{display:flex;align-items:center;gap:10px;font-family:Roboto,sans-serif;font-weight:700;font-size:22px;color:var(--ink);letter-spacing:-.02em}
  .logo img{width:48px;height:48px;border-radius:12px;display:block}
  .logo span{color:var(--navy)}
  nav{display:flex;gap:16px;margin-left:auto;align-items:center}
  nav a{color:var(--ink);font-size:15px}
  .btn,button.btn{display:inline-flex;align-items:center;background:var(--navy);color:#fff;border:0;border-radius:8px;padding:10px 18px;font:inherit;font-weight:500;cursor:pointer}
  .btn:hover,button.btn:hover{background:var(--navy-h);color:#fff}
  form{margin:0}
  .wrap{max-width:720px;margin:0 auto;padding:36px 22px 72px}
  .wrap.wide{max-width:1120px}
  .desk{display:grid;grid-template-columns:200px 1fr;gap:18px;align-items:start}
  .side{background:#fff;border:1px solid var(--line);border-radius:24px;padding:10px}
  .side a{display:block;padding:10px 14px;border-radius:12px;color:var(--ink);font-weight:500}
  .side a.on,.side a.on:hover{background:var(--navy);color:#fff}
  .panel{background:var(--card);border:1px solid var(--line);border-radius:24px;padding:28px}
  .panel form + h2,.panel form + p,.panel form + img,.panel form + table,.shot{margin-top:18px}
  .grid{display:grid;grid-template-columns:1.2fr 1.2fr .6fr;gap:12px}
  .shot{width:100%;max-height:420px;object-fit:contain;background:#eef1f5;border-radius:16px}
  h1{font-family:Roboto,"Noto Sans Georgian",sans-serif;font-size:32px;line-height:1.2;margin:0 0 16px}
  h2{font-size:20px;margin:22px 0 12px}
  pre{background:#141719;color:#f9fafa;padding:14px 16px;border-radius:16px;overflow-x:auto}
  label{display:block;margin:0 0 14px;font-weight:500}
  input{display:block;width:100%;margin-top:6px;padding:12px 14px;border:1px solid var(--line);border-radius:10px;font:inherit;background:#fff}
  input:focus{outline:2px solid #c5d7ea;border-color:var(--navy)}
  small{display:block;color:var(--muted);font-weight:400;margin-top:4px}
  .err{color:#9b2c2c;font-weight:500}
  .ok{color:#1d6b3a;font-weight:500}
  .tabs{display:flex;gap:8px;margin:0 0 18px}
  .tabs a{padding:8px 14px;border-radius:999px;border:1px solid var(--line);color:var(--ink)}
  .tabs a.on{background:var(--navy);color:#fff;border-color:var(--navy)}
  table{width:100%;border-collapse:collapse;margin:0 0 18px}
  th,td{border-bottom:1px solid var(--line);padding:12px 10px;text-align:left;vertical-align:top}
  th{color:var(--muted);font-weight:500}
  .kv th{width:42%}
  .muted{color:var(--muted)}
  @media (max-width:800px){
    .desk,.grid{grid-template-columns:1fr}
    .panel{padding:20px 16px;border-radius:16px}
    h1{font-size:26px}
  }
</style>
CSS;
    }
}
