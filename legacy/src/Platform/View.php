<?php

declare(strict_types=1);

namespace Carapi\Platform;

use Carapi\SiteChrome;

final class View
{
    /** @param array<string, string> $errors @param array<string, mixed> $old */
    public static function login(array $errors, array $old): void
    {
        $fields = self::field('ელფოსტა', 'email', 'email', $old, $errors, 'username')
            . '<div class="fld"><div class="lbl"><span>პაროლი</span><a href="/forgot-password">დაგავიწყდათ პაროლი?</a></div>'
            . '<input class="inp" type="password" name="password" required autocomplete="current-password" aria-label="პაროლი">'
            . (isset($errors['password']) ? '<p class="hint err">' . self::e($errors['password']) . '</p>' : '')
            . '</div>';
        self::auth(
            'შედით GeoAPI ანგარიშში',
            '01',
            'შესვლა',
            'შედით <span>GeoAPI ანგარიშში</span>',
            'მართეთ თქვენი პროექტები, API გასაღებები, დაშვებული დომენები და მოთხოვნების მოხმარება.',
            $fields,
            '/login',
            'შესვლა',
            $errors,
            '<p class="alt">ჯერ არ გაქვთ ანგარიში? <a href="/register">ანგარიშის შექმნა</a></p>',
            self::loginAside()
        );
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    public static function register(array $errors, array $old): void
    {
        $checked = ((string) ($old['accept_terms'] ?? '')) === '1' ? ' checked' : '';
        $fields = '<p class="hint">გაეცანით <a href="/privacy">კონფიდენციალურობის პოლიტიკას</a>. გვერდის გაცნობა არ არის თანხმობა მონაცემების ნებისმიერი მიზნით დამუშავებაზე ან მარკეტინგზე.</p>'
            . self::field('სახელი', 'first_name', 'text', $old, $errors, 'given-name')
            . self::field('გვარი', 'last_name', 'text', $old, $errors, 'family-name')
            . self::field('ელფოსტა', 'email', 'email', $old, $errors, 'email')
            . self::field('პაროლი', 'password', 'password', [], $errors, 'new-password', 'პაროლი უნდა შეიცავდეს მინიმუმ 15 სიმბოლოს.')
            . self::field('გაიმეორეთ პაროლი', 'password_confirmation', 'password', [], $errors, 'new-password')
            . '<label class="chk"><input type="checkbox" name="accept_terms" value="1"' . $checked . '> <span>ვეთანხმები <a href="/terms">გამოყენების წესებს</a>. ეს მარკეტინგის ან მონაცემთა ყველა დამუშავების თანხმობა არ არის.</span></label>'
            . (isset($errors['accept_terms']) ? '<p class="hint err">' . self::e($errors['accept_terms']) . '</p>' : '');
        self::auth(
            'შექმენით GeoAPI ანგარიში',
            '02',
            'რეგისტრაცია',
            'შექმენით <span>GeoAPI ანგარიში</span>',
            'დარეგისტრირდით, დაადასტურეთ ელფოსტა და შექმენით პროექტი, რათა გამოიყენოთ GeoAPI-ის ვიჯეტი ან სერვერული API. თითო პროექტისთვის მოქმედებს მოთხოვნების ლიმიტი: 60 მოთხოვნა წუთში და 10&nbsp;000 წარმატებული მოთხოვნა კალენდარულ თვეში.',
            $fields,
            '/register',
            'ანგარიშის შექმნა',
            $errors,
            '<p class="alt">უკვე გაქვთ ანგარიში? <a href="/login">შესვლა</a></p>',
            self::registerAside()
        );
    }

    /**
     * @param array<string, mixed> $user
     * @param list<array<string, mixed>> $projects
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    public static function account(array $user, array $projects = [], array $errors = [], array $old = []): void
    {
        $notice = $user['email_verified_at'] === null
            ? '<p class="err">ელფოსტა ჯერ დადასტურებული არ არის. პროექტის შექმნა შეგიძლიათ. საიდუმლო API გასაღების შექმნა და გამოყენება დადასტურების შემდეგ იქნება შესაძლებელი.</p>'
            : '';
        $cards = '';
        foreach ($projects as $project) {
            $active = (string) $project['status'] === 'active';
            $url = trim((string) ($project['website_url'] ?? ''));
            $urlHtml = $url === '' ? '' : '<span class="url">' . self::e($url) . '</span>';
            $cards .= '<a class="pcard" href="/account/projects/' . (int) $project['id'] . '"><span class="pcard-main"><b>'
                . self::e((string) $project['name']) . '</b>' . $urlHtml . '</span><span class="' . ($active ? 'pill ok' : 'pill') . '">'
                . self::e($active ? 'აქტიური' : 'გამორთული') . '</span>' . SiteChrome::icon('arrow') . '</a>';
        }
        if ($cards === '') {
            $cards = '<p class="hint">ჯერ არც ერთი პროექტი არ შეგიქმნიათ.</p>';
        }
        $resend = $user['email_verified_at'] === null
            ? '<form method="post" action="/email/verification/resend"><input type="hidden" name="_csrf" value="' . self::e(Session::csrf()) . '"><button class="btn btn-g" type="submit">დადასტურების წერილის ხელახლა გაგზავნა</button></form>'
            : '';
        $main = '<div class="mh"><div><h1>ჩემი პროექტები</h1><p>აირჩიეთ პროექტი, რათა მართოთ API გასაღებები, დაშვებული დომენები და მოხმარების სტატისტიკა.</p><p><a href="/account/products">API პროდუქტები და წვდომა</a></p></div></div>'
            . $notice . $resend
            . (isset($errors['form']) ? '<p class="err">' . self::e($errors['form']) . '</p>' : '')
            . '<div class="split"><section class="plist">' . $cards . '</section><section class="pn"><h2>ახალი პროექტის შექმნა</h2>'
            . self::projectForm('/account', 'create_project', 'პროექტის შექმნა', $old, $errors, null)
            . '</section></div>';
        self::desk(
            'ჩემი პროექტები',
            $main,
            'overview',
            null,
            trim((string) $user['first_name'] . ' ' . (string) $user['last_name']),
            (string) $user['email'],
            $projects
        );
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $project
     * @param list<array<string, mixed>> $keys
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     * @param array<string, mixed>|null $widget
     */
    public static function project(array $user, array $project, array $keys, ?string $revealed, array $errors, array $old, ?array $widget = null): void
    {
        $id = (int) $project['id'];
        $usage = UsageLimiter::fromEnv()->storeStats($id);
        $limit = $usage['limit'] ?? 0;
        $used = $usage['used'] ?? 0;
        $percent = $limit > 0 ? (int) min(100, round(((int) $used / (int) $limit) * 100)) : 0;
        $origins = $widget === null ? [] : $widget['allowed_origins'];
        $activeKeys = 0;
        foreach ($keys as $key) {
            if ($key['revoked_at'] === null) {
                $activeKeys++;
            }
        }
        if ($widget !== null && $widget['enabled']) {
            $activeKeys++;
        }
        $kpis = '<div class="kp"><div><small>მოთხოვნები ამ თვეში</small><b class="num" id="usage-month-used">' . ($usage === null ? '—' : self::e((string) $used)) . '</b><div class="d">მოთხოვნების ლიმიტი ' . ($usage === null ? '—' : self::e((string) $limit)) . '</div></div>'
            . '<div><small>მოხმარება</small><b class="num">' . $percent . '%</b><div class="bar"><i style="width:' . $percent . '%"></i></div></div>'
            . '<div><small>დარჩენილი მოთხოვნები</small><b class="num" id="usage-month-remaining">' . ($usage === null ? '—' : self::e((string) $usage['remaining'])) . '</b><div class="d">განახლება ' . self::e(self::day($usage === null ? null : (string) $usage['reset_at'])) . '</div></div>'
            . '<div><small>დაშვებული დომენები</small><b class="num">' . count($origins) . '</b><div class="d">' . $activeKeys . ' აქტიური გასაღები</div></div></div>';
        if ($usage === null) {
            $kpis .= '<p class="err">მოთხოვნების ლიმიტის კონფიგურაცია ვერ მოიძებნა. დაგვიკავშირდით: hello@geoapi.ge.</p>';
        }
        $report = self::usageReport($usage);

        $rows = '';
        $cards = '';
        if ($widget !== null) {
            $public = (string) $widget['public_key'];
            $masked = substr($public, 0, 16) . '…' . substr($public, -4);
            $state = $widget['enabled'] ? 'აქტიური' : 'გამორთული';
            $rows .= self::keyRow('ვიჯეტი საიტზე', 'საჯარო API გასაღები', $masked, '—', $state, $public, null);
            $cards .= self::keyCard('ვიჯეტი საიტზე', 'საჯარო API გასაღები', $masked, '—', $state, $public);
        }
        foreach ($keys as $key) {
            $masked = 'geoapi_sk_' . (string) $key['key_selector'] . '_…';
            $state = $key['revoked_at'] !== null ? 'გაუქმებული' : 'აქტიური';
            $when = self::ago($key['last_used_at'] !== null ? (string) $key['last_used_at'] : null);
            $rows .= self::keyRow((string) $key['name'], 'საიდუმლო API გასაღები', $masked, $when, $state, $masked, $key['revoked_at'] === null ? (int) $key['id'] : null);
            $cards .= self::keyCard((string) $key['name'], 'საიდუმლო API გასაღები', $masked, $when, $state, $masked);
        }
        $reveal = '';
        if ($revealed !== null) {
            $reveal = '<div class="reveal"><p class="err">ეს გასაღები სრულად მხოლოდ ერთხელ გამოჩნდება. შეინახეთ უსაფრთხო ადგილას.</p><input class="inp mono" id="new-key" readonly value="'
                . self::e($revealed) . '"><button class="btn btn-g" type="button" data-copy="new-key">გასაღების კოპირება</button></div>';
        }
        $verified = $user['email_verified_at'] !== null;
        $keyForm = $verified
            ? self::keyForm($id, $old, $errors)
            : '<p class="err">საიდუმლო API გასაღების შესაქმნელად ჯერ დაადასტურეთ ელფოსტა.</p>';
        $empty = $rows === '' ? '<p class="hint">საიდუმლო API გასაღები ჯერ არ შეგიქმნიათ.</p>' : '';

        $main = '<div class="mh"><div><div class="dl"><b>' . self::e((string) $project['name']) . '</b></div><h1>API გასაღებები</h1><p>'
            . $activeKeys . ' აქტიური გასაღები</p></div>'
            . '<a class="btn btn-l" href="#key-form">' . SiteChrome::icon('plus') . 'ახალი გასაღების შექმნა</a></div>'
            . $kpis
            . (isset($errors['form']) ? '<p class="err">' . self::e($errors['form']) . '</p>' : '')
            . '<div class="sec2" id="keys"><div class="sh"><div><h2>API გასაღებები</h2><p>საიდუმლო API გასაღები სრულად მხოლოდ შექმნისას ჩანს. გასაღების გაუქმების შემდეგ მისი გამოყენებით ახალი მოთხოვნების ავტორიზაცია ვეღარ შესრულდება.</p></div></div>'
            . $reveal
            . ($rows === '' ? '' : '<table class="tb2"><thead><tr><th>სახელი</th><th>ტიპი</th><th>გასაღები</th><th>ბოლო გამოყენება</th><th>სტატუსი</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table>')
            . '<div class="kc">' . $cards . '</div>' . $empty . $keyForm . '</div>'
            . '<div class="two"><div class="pn" id="domains"><div class="sh"><div><h2>დაშვებული დომენები</h2><p>ვიჯეტი მხოლოდ ჩაწერილი origin-იდან მუშაობს. Origin არის პროტოკოლი, დომენი და საჭიროების შემთხვევაში პორტი. გვერდის ბილიკი და wildcard (*) არ მიიღება.</p></div></div>'
            . self::domainBlock($id, $widget, $errors)
            . '</div><div class="pn" id="style"><div class="sh"><div><h2>ვიჯეტის ჩასმა</h2><p>ჩასვით ეს კოდი ვებსაიტზე. აქ უკვე თქვენი საჯარო API გასაღებია და მისი ჩვენება HTML-ში დასაშვებია.</p></div></div>'
            . self::embedBlock($id, $widget)
            . '</div></div>'
            . '<div class="sec2" id="usage"><div class="sh"><div><h2>მოხმარების სტატისტიკა</h2><p>თვიური რიცხვი UTC კალენდარული თვის მრიცხველია. დიაგრამა ბოლო 30 UTC დღეს აჩვენებს, დღევანდელი დღის ჩათვლით.</p></div></div>' . $report . '</div>'
            . '<div class="sec2" id="overview"><div class="sh"><div><h2>პროექტის პარამეტრები</h2></div></div>'
            . self::projectForm('/account', 'update_project', 'ცვლილებების შენახვა', ['name' => $project['name'], 'website_url' => $project['website_url'], 'project_id' => $id], $errors, $id)
            . self::statusForm($id, (string) $project['status'])
            . '</div>';
        self::desk(
            'პროექტი',
            $main,
            'keys',
            $project,
            trim((string) $user['first_name'] . ' ' . (string) $user['last_name']),
            (string) $user['email']
        );
    }

    /** @param array<string, string> $errors */
    /** @param array<string, mixed> $user @param array<string, mixed> $project */
    public static function playground(array $user, array $project): void
    {
        $id = (int) $project['id'];
        $options = '';
        foreach (Playground::clientOps() as $operation) {
            $label = 'GET ' . $operation['path'] . ' — ' . $operation['description'];
            $options .= '<option value="' . self::e($operation['path']) . '">' . self::e($label) . '</option>';
        }
        $fieldMeta = [
            'slug' => ['ბრენდის კოდი (slug)', 'ბრენდების სიაში დაბრუნებული slug.', false],
            'id' => ['მოდელის ID', 'თაობის საჯარო ID აიღეთ თაობების სიის პასუხიდან. ჯგუფის სხვა შიდა ნომერი საჯარო ID არ არის.', false],
            'page' => ['გვერდი', 'არასავალდებულოა. ნაგულისხმევი 1, მაქსიმუმი 1000000.', true],
            'per_page' => ['ჩანაწერები გვერდზე', 'არასავალდებულოა. ნაგულისხმევი 50, დიაპაზონი 1-დან 100-მდე.', true],
        ];
        $fields = '';
        foreach ($fieldMeta as $name => [$label, $hint, $open]) {
            $fields .= '<div class="fld" id="field-' . $name . '"' . ($open ? '' : ' hidden') . '>'
                . '<label class="lbl" id="label-' . $name . '" for="playground-' . $name . '">' . self::e($label) . '</label>'
                . '<input class="inp" id="playground-' . $name . '" type="text" name="' . $name . '"' . ($open ? '' : ' disabled') . '>'
                . '<p class="hint" id="hint-' . $name . '">' . self::e($hint) . '</p></div>';
        }
        $ops = (string) json_encode(Playground::clientOps(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $main = '<div class="pn"><div class="sh"><div><h1>API Playground</h1>'
            . '<p>გამოცადეთ GeoAPI-ის მოთხოვნები პროექტის კაბინეტიდან, ბრენდიდან კონკრეტულ მოდიფიკაციამდე.</p></div></div>'
            . '<p class="hint">Playground-ის წარმატებული მოთხოვნები ითვლება თქვენი პროექტის API კვოტაში.</p>'
            . '<form id="playground-form" method="post" action="/account/projects/' . $id . '/playground/run">'
            . '<input type="hidden" name="_csrf" value="' . self::e(Session::csrf()) . '">'
            . '<div class="fld"><label class="lbl" for="playground-operation">მარშრუტი</label><select class="inp" id="playground-operation" name="operation">' . $options . '</select>'
            . '<p class="hint" id="playground-desc">ბრენდების სიის მიღება. მეთოდი: GET.</p></div>'
            . $fields
            . '<button class="btn btn-l" id="playground-send" type="submit">მოთხოვნის გაგზავნა</button>'
            . '</form>'
            . '<div class="fld"><div class="lbl"><span>curl</span></div><pre class="json" id="playground-curl"></pre>'
            . '<button class="btn btn-g" id="playground-copy" type="button">curl-ის კოპირება</button></div>'
            . '<div class="fld" aria-live="polite"><span class="pill" id="playground-badge">მზადაა</span> '
            . '<span id="playground-status">მზადაა</span> <span id="playground-time"></span></div>'
            . '<pre class="json" id="playground-headers"></pre>'
            . '<p class="hint" id="playground-empty">პასუხი ჯერ არ არის.</p>'
            . '<pre class="json" id="playground-json"></pre>'
            . '<button class="btn btn-g" id="playground-copy-json" type="button">JSON-ის კოპირება</button>'
            . '<p><a href="/account/projects/' . $id . '">პროექტის კაბინეტში დაბრუნება</a></p></div>'
            . '<script type="application/json" id="playground-ops">' . $ops . '</script>'
            . <<<'JS'
<script>
(function () {
  var ops = JSON.parse(document.getElementById("playground-ops").textContent);
  var form = document.getElementById("playground-form");
  var select = document.getElementById("playground-operation");
  var button = document.getElementById("playground-send");
  var badge = document.getElementById("playground-badge");
  var status = document.getElementById("playground-status");
  var time = document.getElementById("playground-time");
  var headers = document.getElementById("playground-headers");
  var empty = document.getElementById("playground-empty");
  var out = document.getElementById("playground-json");
  var curl = document.getElementById("playground-curl");
  var desc = document.getElementById("playground-desc");
  var sending = false;
  var states = {200: "წარმატებული", 401: "ავტორიზაცია საჭიროა", 403: "მოთხოვნა არ არის დაშვებული", 404: "ჩანაწერი ვერ მოიძებნა", 419: "სესია ამოიწურა", 422: "პარამეტრი არასწორია", 429: "მოთხოვნების ლიმიტი მიღწეულია", 503: "სერვერის შეცდომა"};
  function opFor(path) {
    for (var i = 0; i < ops.length; i++) if (ops[i].path === path) return ops[i];
    return null;
  }
  function sync() {
    var op = opFor(select.value);
    var names = op ? op.fields : [];
    desc.textContent = (op ? op.description : "") + ". მეთოდი: GET.";
    ["slug", "id", "page", "per_page"].forEach(function (name) {
      var wrap = document.getElementById("field-" + name);
      var input = document.getElementById("playground-" + name);
      var on = names.indexOf(name) !== -1;
      wrap.hidden = !on;
      input.disabled = !on;
    });
    var label = document.getElementById("label-id");
    var hint = document.getElementById("hint-id");
    if (select.value.indexOf("/generations/") === 0 || select.value.indexOf("/api/v1/generations/") === 0) {
      label.textContent = "თაობის საჯარო ID";
      hint.textContent = "თაობის საჯარო ID აიღეთ თაობების სიის პასუხიდან. ჯგუფის სხვა შიდა ნომერი საჯარო ID არ არის.";
    } else if (select.value.indexOf("/modifications/") !== -1 && select.value.indexOf("/generations/") === -1) {
      label.textContent = "მოდიფიკაციის ID";
      hint.textContent = "აიღეთ მოდიფიკაციების სიაში დაბრუნებული id.";
    } else {
      label.textContent = "მოდელის ID";
      hint.textContent = "აიღეთ მოდელების სიაში დაბრუნებული id.";
    }
    var path = select.value;
    var slug = document.getElementById("playground-slug");
    var id = document.getElementById("playground-id");
    var page = document.getElementById("playground-page");
    var perPage = document.getElementById("playground-per_page");
    if (!slug.disabled && /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug.value)) path = path.replace("{slug}", slug.value);
    if (!id.disabled && /^[1-9][0-9]{0,18}$/.test(id.value)) path = path.replace("{id}", id.value);
    var query = [];
    if (!page.disabled && /^[1-9][0-9]*$/.test(page.value)) query.push("page=" + page.value);
    if (!perPage.disabled && /^[1-9][0-9]*$/.test(perPage.value)) query.push("per_page=" + perPage.value);
    curl.textContent = "curl " + location.origin + path + (query.length ? "?" + query.join("&") : "") + " \\\n  -H \"Authorization: Bearer YOUR_API_KEY\"";
  }
  function copyText(value) {
    if (navigator.clipboard && value) navigator.clipboard.writeText(value);
  }
  select.addEventListener("change", sync);
  form.addEventListener("input", sync);
  document.getElementById("playground-copy").addEventListener("click", function () { copyText(curl.textContent); });
  document.getElementById("playground-copy-json").addEventListener("click", function () { copyText(out.textContent); });
  sync();
  form.addEventListener("submit", function (event) {
    event.preventDefault();
    if (sending) return;
    sending = true;
    button.disabled = true;
    badge.textContent = "იგზავნება";
    status.textContent = "იგზავნება...";
    time.textContent = "";
    headers.textContent = "";
    out.textContent = "";
    empty.hidden = true;
    var started = performance.now();
    fetch(form.action, {method: "POST", body: new FormData(form), headers: {Accept: "application/json"}, credentials: "same-origin"})
      .then(function (response) { return response.json().then(function (payload) { return {status: response.status, payload: payload}; }); })
      .then(function (result) {
        var payload = result.payload || {};
        var code = payload.status || result.status;
        badge.textContent = String(code);
        status.textContent = states[code] || "სერვერის შეცდომა";
        time.textContent = Math.round(performance.now() - started) + " მწმ";
        var quota = payload.headers || {};
        headers.textContent = Object.keys(quota).map(function (key) { return key + ": " + quota[key]; }).join("\n");
        out.textContent = JSON.stringify(payload.body || payload.error || payload, null, 2);
        empty.hidden = out.textContent !== "";
      })
      .catch(function () {
        badge.textContent = "შეცდომა";
        status.textContent = "ქსელის შეცდომა";
        time.textContent = Math.round(performance.now() - started) + " მწმ";
        out.textContent = "პასუხი ვერ წაიკითხა.";
        empty.hidden = true;
      })
      .then(function () { sending = false; button.disabled = false; });
  });
})();
</script>
JS;
        self::desk(
            'API Playground',
            $main,
            'playground',
            $project,
            trim((string) $user['first_name'] . ' ' . (string) $user['last_name']),
            (string) $user['email']
        );
    }

    /** @param array<string, mixed> $user @param array<string, mixed> $project */
    public static function explorer(array $user, array $project): void
    {
        $levels = [
            'brand' => 'ბრენდი',
            'model' => 'მოდელი',
            'generation' => 'თაობა',
            'modification' => 'მოდიფიკაცია',
        ];
        $guides = [
            'brand' => '',
            'model' => 'ჯერ აირჩიეთ ბრენდი.',
            'generation' => 'ჯერ აირჩიეთ მოდელი.',
            'modification' => 'ჯერ აირჩიეთ თაობა.',
        ];
        $id = (int) $project['id'];
        $fields = '';
        foreach ($levels as $name => $label) {
            $fields .= '<div class="fld"><label class="lbl" for="explorer-' . $name . '">' . self::e($label) . '</label>'
                . '<select class="inp" id="explorer-' . $name . '" name="' . $name . '" disabled aria-describedby="explorer-' . $name . '-help">'
                . '<option value="">' . ($name === 'brand' ? 'აირჩიეთ ბრენდი' : 'ჯერ არ არის ხელმისაწვდომი') . '</option></select>'
                . '<p class="hint" id="explorer-' . $name . '-help">' . self::e($guides[$name]) . '</p></div>';
            if ($name === 'brand') {
                $fields .= '<button class="btn btn-g" id="explorer-more" type="button" hidden disabled>მეტი ბრენდის ჩატვირთვა</button>';
            }
            if ($name === 'model') {
                $fields .= '<p class="hint" id="explorer-model-status" aria-live="polite"></p>'
                    . '<button class="btn btn-g" id="explorer-more-models" type="button" hidden disabled>მეტი მოდელის ჩატვირთვა</button>';
            }
            if ($name === 'generation') {
                $fields .= '<p class="hint" id="explorer-generation-status" aria-live="polite"></p>'
                    . '<button class="btn btn-g" id="explorer-more-generations" type="button" hidden disabled>მეტი თაობის ჩატვირთვა</button>';
            }
            if ($name === 'modification') {
                $fields .= '<p class="hint" id="explorer-modification-status" aria-live="polite"></p>'
                    . '<button class="btn btn-g" id="explorer-more-modifications" type="button" hidden disabled>მეტი მოდიფიკაციის ჩატვირთვა</button>';
            }
        }
        $main = '<div class="pn explorer"><div class="sh"><div><h1>ავტომობილების კატალოგი</h1>'
            . '<p>მოძებნეთ ავტომობილი ბრენდის, მოდელის, თაობისა და მოდიფიკაციის მიხედვით.</p></div></div>'
            . '<p class="hint">Explorer-ის წარმატებული მოთხოვნები ითვლება თქვენი პროექტის API კვოტაში.</p>'
            . '<p class="hint" id="explorer-status" aria-live="polite">ბრენდები იტვირთება.</p>'
            . '<form id="explorer-form" method="post" action="/account/projects/' . $id . '/playground/run">'
            . '<input type="hidden" name="_csrf" value="' . self::e(Session::csrf()) . '">'
            . $fields . '</form>'
            . '<div class="pn" id="explorer-detail" hidden><h2 id="explorer-detail-title">მოდიფიკაცია</h2><dl id="explorer-detail-list" aria-labelledby="explorer-detail-title" aria-live="polite" aria-atomic="true"></dl></div></div>'
            . <<<'JS'
<script>
(function () {
  var state = {brand: "", model: "", generation: "", modification: "", page: 0, total: 0, loading: false, modelPage: 0, modelTotal: 0, modelLoading: false, requestId: 0, abort: null, generationPage: 0, generationTotal: 0, generationLoading: false, generationRequestId: 0, generationAbort: null, modificationPage: 0, modificationTotal: 0, modificationLoading: false, modificationRequestId: 0, modificationAbort: null, detailRequestId: 0, detailAbort: null};
  var form = document.getElementById("explorer-form");
  var brand = document.getElementById("explorer-brand");
  var model = document.getElementById("explorer-model");
  var generation = document.getElementById("explorer-generation");
  var modification = document.getElementById("explorer-modification");
  var status = document.getElementById("explorer-status");
  var modelStatus = document.getElementById("explorer-model-status");
  var generationStatus = document.getElementById("explorer-generation-status");
  var modificationStatus = document.getElementById("explorer-modification-status");
  var more = document.getElementById("explorer-more");
  var moreModels = document.getElementById("explorer-more-models");
  var moreGenerations = document.getElementById("explorer-more-generations");
  var moreModifications = document.getElementById("explorer-more-modifications");
  var detail = document.getElementById("explorer-detail");
  var detailList = document.getElementById("explorer-detail-list");
  var brandHelp = document.getElementById("explorer-brand-help");
  var modelHelp = document.getElementById("explorer-model-help");
  var generationHelp = document.getElementById("explorer-generation-help");
  var modificationHelp = document.getElementById("explorer-modification-help");
  function setHelp(node, text) {
    if (node.textContent !== text) node.textContent = text;
  }
  var seen = {};
  var seenModels = {};
  var seenGenerations = {};
  var seenModifications = {};
  var messages = {401: "სესია ამოიწურა. შედით ანგარიშში.", 403: "მოთხოვნა არ არის დაშვებული.", 404: "ჩანაწერი ვერ მოიძებნა.", 419: "სესია ამოიწურა. განაახლეთ გვერდი.", 422: "პარამეტრი არასწორია.", 429: "მოთხოვნების ლიმიტი მიღწეულია."};
  function addBrands(rows) {
    rows.forEach(function (row) {
      if (!row || typeof row.slug !== "string" || row.slug === "" || typeof row.name !== "string" || seen[row.slug]) return;
      seen[row.slug] = true;
      var option = document.createElement("option");
      option.value = row.slug;
      option.textContent = row.name;
      brand.appendChild(option);
    });
  }
  function finish() {
    state.loading = false;
    if (!more.hidden) more.disabled = false;
  }
  function load() {
    if (state.loading) return;
    var loaded = Object.keys(seen).length;
    if (state.page > 0 && (loaded >= state.total || state.page >= 1000000)) return;
    state.loading = true;
    more.disabled = true;
    status.textContent = "ბრენდები იტვირთება.";
    var body = new FormData();
    body.append("_csrf", form.querySelector("[name=_csrf]").value);
    body.append("operation", "/api/v1/brands");
    body.append("page", String(state.page + 1));
    body.append("per_page", "50");
    fetch(form.action, {method: "POST", body: body, headers: {Accept: "application/json"}, credentials: "same-origin"})
      .then(function (response) { return response.json().then(function (payload) { return {status: response.status, payload: payload}; }); })
      .then(function (result) {
        var code = result.status;
        var payload = result.payload || {};
        var catalog = payload.body || {};
        var data = catalog.data;
        var total = catalog.meta ? Number(catalog.meta.total) : NaN;
        if (code !== 200 || !Array.isArray(data) || !Number.isFinite(total) || total < 0) {
          status.textContent = messages[code] || (code >= 500 ? "სერვერის შეცდომა." : "პასუხის ფორმა მოულოდნელია.");
          showBrandRetry();
          return;
        }
        state.page += 1;
        state.total = total;
        addBrands(data);
        loaded = Object.keys(seen).length;
        if (total === 0) {
          status.textContent = "ბრენდები ვერ მოიძებნა.";
          setHelp(brandHelp, "ბრენდები ვერ მოიძებნა.");
          brand.disabled = true;
          more.hidden = true;
          return;
        }
        brand.disabled = false;
        setHelp(brandHelp, "");
        more.textContent = "მეტი ბრენდის ჩატვირთვა";
        var done = loaded >= total || data.length === 0;
        status.textContent = "ჩატვირთულია " + loaded + " / " + total;
        more.hidden = done;
        more.disabled = done;
      })
      .catch(function () {
        status.textContent = "ქსელის შეცდომა.";
        showBrandRetry();
      })
      .then(finish);
  }
  function showBrandRetry() {
    if (Object.keys(seen).length !== 0) return;
    more.hidden = false;
    more.textContent = "თავიდან ცდა";
    setHelp(brandHelp, "თავიდან ცდა ხელმისაწვდომია.");
  }
  function resetModels() {
    resetGenerations();
    state.model = "";
    state.modelPage = 0;
    state.modelTotal = 0;
    seenModels = {};
    state.requestId += 1;
    state.modelLoading = false;
    if (state.abort) state.abort.abort();
    while (model.options.length > 1) model.remove(1);
    model.value = "";
    model.disabled = true;
    moreModels.hidden = true;
    moreModels.disabled = true;
    moreModels.textContent = "მეტი მოდელის ჩატვირთვა";
    modelStatus.textContent = "";
    setHelp(modelHelp, state.brand === "" ? "ჯერ აირჩიეთ ბრენდი." : "");
    setHelp(generationHelp, "ჯერ აირჩიეთ მოდელი.");
    setHelp(modificationHelp, "ჯერ აირჩიეთ თაობა.");
  }
  function addModels(rows) {
    rows.forEach(function (row) {
      if (!row || typeof row.id !== "string" || !/^[1-9][0-9]{0,18}$/.test(row.id) || typeof row.name !== "string" || seenModels[row.id]) return;
      seenModels[row.id] = true;
      var option = document.createElement("option");
      option.value = row.id;
      option.textContent = row.name;
      model.appendChild(option);
    });
  }
  function showModelRetry() {
    if (Object.keys(seenModels).length === 0) {
      moreModels.hidden = false;
      moreModels.textContent = "თავიდან ცდა";
      setHelp(modelHelp, "თავიდან ცდა ხელმისაწვდომია.");
    }
  }
  function loadModels() {
    if (state.modelLoading) return;
    var slug = state.brand;
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) {
      modelStatus.textContent = messages[422];
      return;
    }
    var loaded = Object.keys(seenModels).length;
    if (state.modelPage > 0 && (loaded >= state.modelTotal || state.modelPage >= 1000000)) return;
    var current = ++state.requestId;
    if (state.abort) state.abort.abort();
    state.abort = typeof AbortController === "function" ? new AbortController() : null;
    state.modelLoading = true;
    moreModels.disabled = true;
    modelStatus.textContent = "მოდელები იტვირთება.";
    var body = new FormData();
    body.append("_csrf", form.querySelector("[name=_csrf]").value);
    body.append("operation", "/api/v1/brands/{slug}/models");
    body.append("slug", slug);
    body.append("page", String(state.modelPage + 1));
    body.append("per_page", "50");
    fetch(form.action, {method: "POST", body: body, headers: {Accept: "application/json"}, credentials: "same-origin", signal: state.abort ? state.abort.signal : undefined})
      .then(function (response) { return response.json().then(function (payload) { return {status: response.status, payload: payload}; }); })
      .then(function (result) {
        if (current !== state.requestId || brand.value !== slug) return;
        var code = result.status;
        var catalog = (result.payload || {}).body || {};
        var data = catalog.data;
        var total = catalog.meta ? Number(catalog.meta.total) : NaN;
        if (code !== 200 || !Array.isArray(data) || !Number.isFinite(total) || total < 0) {
          modelStatus.textContent = messages[code] || (code >= 500 ? "სერვერის შეცდომა." : "პასუხის ფორმა მოულოდნელია.");
          showModelRetry();
          return;
        }
        state.modelPage += 1;
        state.modelTotal = total;
        addModels(data);
        loaded = Object.keys(seenModels).length;
        if (total === 0) {
          modelStatus.textContent = "ამ ბრენდს მოდელი არ აქვს.";
          setHelp(modelHelp, "ამ ბრენდს მოდელი არ აქვს.");
          model.disabled = true;
          moreModels.hidden = true;
          return;
        }
        model.options[0].textContent = "აირჩიეთ მოდელი";
        model.disabled = false;
        setHelp(modelHelp, "");
        moreModels.textContent = "მეტი მოდელის ჩატვირთვა";
        var done = loaded >= total || data.length === 0;
        modelStatus.textContent = "ჩატვირთულია " + loaded + " / " + total;
        moreModels.hidden = done;
        moreModels.disabled = done;
      })
      .catch(function (error) {
        if (current !== state.requestId || (error && error.name === "AbortError")) return;
        modelStatus.textContent = "ქსელის შეცდომა.";
        showModelRetry();
      })
      .then(function () {
        if (current !== state.requestId) return;
        state.modelLoading = false;
        if (!moreModels.hidden) moreModels.disabled = false;
      });
  }
  function generationLabel(row) {
    if (typeof row.display_label === "string" && row.display_label !== "") return row.display_label;
    var parts = [];
    if (typeof row.name === "string" && row.name !== "") parts.push(row.name);
    if (typeof row.period_text === "string" && row.period_text !== "") parts.push(row.period_text);
    return parts.length ? parts.join(" · ") : row.id;
  }
  function resetGenerations() {
    resetModifications();
    state.generation = "";
    state.modification = "";
    state.generationPage = 0;
    state.generationTotal = 0;
    seenGenerations = {};
    state.generationRequestId += 1;
    state.generationLoading = false;
    if (state.generationAbort) state.generationAbort.abort();
    while (generation.options.length > 1) generation.remove(1);
    generation.value = "";
    modification.value = "";
    generation.disabled = true;
    modification.disabled = true;
    moreGenerations.hidden = true;
    moreGenerations.disabled = true;
    moreGenerations.textContent = "მეტი თაობის ჩატვირთვა";
    generationStatus.textContent = "";
    setHelp(generationHelp, state.model === "" ? "ჯერ აირჩიეთ მოდელი." : "");
    setHelp(modificationHelp, "ჯერ აირჩიეთ თაობა.");
  }
  function addGenerations(rows) {
    rows.forEach(function (row) {
      if (!row || typeof row.id !== "string" || !/^[1-9][0-9]{0,18}$/.test(row.id) || seenGenerations[row.id]) return;
      seenGenerations[row.id] = true;
      var option = document.createElement("option");
      option.value = row.id;
      option.textContent = generationLabel(row);
      generation.appendChild(option);
    });
  }
  function showGenerationRetry() {
    if (Object.keys(seenGenerations).length === 0) {
      moreGenerations.hidden = false;
      moreGenerations.textContent = "თავიდან ცდა";
      setHelp(generationHelp, "თავიდან ცდა ხელმისაწვდომია.");
    }
  }
  function loadGenerations() {
    if (state.generationLoading) return;
    var modelId = state.model;
    if (!/^[1-9][0-9]{0,18}$/.test(modelId)) {
      generationStatus.textContent = messages[422];
      return;
    }
    var loaded = Object.keys(seenGenerations).length;
    if (state.generationPage > 0 && (loaded >= state.generationTotal || state.generationPage >= 1000000)) return;
    var current = ++state.generationRequestId;
    if (state.generationAbort) state.generationAbort.abort();
    state.generationAbort = typeof AbortController === "function" ? new AbortController() : null;
    state.generationLoading = true;
    moreGenerations.disabled = true;
    generationStatus.textContent = "თაობები იტვირთება.";
    var body = new FormData();
    body.append("_csrf", form.querySelector("[name=_csrf]").value);
    body.append("operation", "/api/v1/models/{id}/generations");
    body.append("id", modelId);
    body.append("page", String(state.generationPage + 1));
    body.append("per_page", "50");
    fetch(form.action, {method: "POST", body: body, headers: {Accept: "application/json"}, credentials: "same-origin", signal: state.generationAbort ? state.generationAbort.signal : undefined})
      .then(function (response) { return response.json().then(function (payload) { return {status: response.status, payload: payload}; }); })
      .then(function (result) {
        if (current !== state.generationRequestId || model.value !== modelId) return;
        var code = result.status;
        var catalog = (result.payload || {}).body || {};
        var data = catalog.data;
        var total = catalog.meta ? Number(catalog.meta.total) : NaN;
        if (code !== 200 || !Array.isArray(data) || !Number.isFinite(total) || total < 0) {
          generationStatus.textContent = messages[code] || (code >= 500 ? "სერვერის შეცდომა." : "პასუხის ფორმა მოულოდნელია.");
          showGenerationRetry();
          return;
        }
        state.generationPage += 1;
        state.generationTotal = total;
        addGenerations(data);
        loaded = Object.keys(seenGenerations).length;
        if (total === 0) {
          generationStatus.textContent = "ამ მოდელს თაობა არ აქვს.";
          setHelp(generationHelp, "ამ მოდელს თაობა არ აქვს.");
          generation.disabled = true;
          moreGenerations.hidden = true;
          return;
        }
        generation.options[0].textContent = "აირჩიეთ თაობა";
        generation.disabled = false;
        setHelp(generationHelp, "");
        moreGenerations.textContent = "მეტი თაობის ჩატვირთვა";
        var done = loaded >= total || data.length === 0;
        generationStatus.textContent = "ჩატვირთულია " + loaded + " / " + total;
        moreGenerations.hidden = done;
        moreGenerations.disabled = done;
      })
      .catch(function (error) {
        if (current !== state.generationRequestId || (error && error.name === "AbortError")) return;
        generationStatus.textContent = "ქსელის შეცდომა.";
        showGenerationRetry();
      })
      .then(function () {
        if (current !== state.generationRequestId) return;
        state.generationLoading = false;
        if (!moreGenerations.hidden) moreGenerations.disabled = false;
      });
  }
  function modificationLabel(row) {
    var parts = [];
    if (typeof row.name === "string" && row.name !== "") parts.push(row.name);
    if (typeof row.engine_volume_cc === "number") parts.push(row.engine_volume_cc + " სმ³");
    if (typeof row.transmission === "string" && row.transmission !== "") parts.push(row.transmission);
    if (typeof row.year_start === "number") parts.push(row.year_end == null ? row.year_start + "–" : row.year_start + "–" + row.year_end);
    return parts.length ? parts.join(" · ") : row.id;
  }
  function clearDetail() {
    state.detailRequestId += 1;
    if (state.detailAbort) state.detailAbort.abort();
    detail.hidden = true;
    while (detailList.firstChild) detailList.removeChild(detailList.firstChild);
  }
  function resetModifications() {
    state.modification = "";
    state.modificationPage = 0;
    state.modificationTotal = 0;
    seenModifications = {};
    state.modificationRequestId += 1;
    state.modificationLoading = false;
    if (state.modificationAbort) state.modificationAbort.abort();
    clearDetail();
    while (modification.options.length > 1) modification.remove(1);
    modification.value = "";
    modification.disabled = true;
    moreModifications.hidden = true;
    moreModifications.disabled = true;
    moreModifications.textContent = "მეტი მოდიფიკაციის ჩატვირთვა";
    modificationStatus.textContent = "";
    setHelp(modificationHelp, state.generation === "" ? "ჯერ აირჩიეთ თაობა." : "");
  }
  function addModifications(rows) {
    rows.forEach(function (row) {
      if (!row || typeof row.id !== "string" || !/^[1-9][0-9]{0,18}$/.test(row.id) || seenModifications[row.id]) return;
      seenModifications[row.id] = true;
      var option = document.createElement("option");
      option.value = row.id;
      option.textContent = modificationLabel(row);
      modification.appendChild(option);
    });
  }
  function showModificationRetry() {
    if (Object.keys(seenModifications).length === 0) {
      moreModifications.hidden = false;
      moreModifications.textContent = "თავიდან ცდა";
      setHelp(modificationHelp, "თავიდან ცდა ხელმისაწვდომია.");
    }
  }
  function addDetailRow(label, value) {
    if (value === null || value === undefined || value === "") return;
    var term = document.createElement("dt");
    var description = document.createElement("dd");
    term.textContent = label;
    description.textContent = String(value);
    detailList.appendChild(term);
    detailList.appendChild(description);
  }
  function loadModifications() {
    if (state.modificationLoading) return;
    var generationId = state.generation;
    if (!/^[1-9][0-9]{0,18}$/.test(generationId)) {
      modificationStatus.textContent = messages[422];
      return;
    }
    var loaded = Object.keys(seenModifications).length;
    if (state.modificationPage > 0 && (loaded >= state.modificationTotal || state.modificationPage >= 1000000)) return;
    var current = ++state.modificationRequestId;
    if (state.modificationAbort) state.modificationAbort.abort();
    state.modificationAbort = typeof AbortController === "function" ? new AbortController() : null;
    state.modificationLoading = true;
    moreModifications.disabled = true;
    modificationStatus.textContent = "მოდიფიკაციები იტვირთება.";
    var body = new FormData();
    body.append("_csrf", form.querySelector("[name=_csrf]").value);
    body.append("operation", "/api/v1/generations/{id}/modifications");
    body.append("id", generationId);
    body.append("page", String(state.modificationPage + 1));
    body.append("per_page", "50");
    fetch(form.action, {method: "POST", body: body, headers: {Accept: "application/json"}, credentials: "same-origin", signal: state.modificationAbort ? state.modificationAbort.signal : undefined})
      .then(function (response) { return response.json().then(function (payload) { return {status: response.status, payload: payload}; }); })
      .then(function (result) {
        if (current !== state.modificationRequestId || generation.value !== generationId) return;
        var code = result.status;
        var catalog = (result.payload || {}).body || {};
        var data = catalog.data;
        var total = catalog.meta ? Number(catalog.meta.total) : NaN;
        if (code !== 200 || !Array.isArray(data) || !Number.isFinite(total) || total < 0) {
          modificationStatus.textContent = messages[code] || (code >= 500 ? "სერვერის შეცდომა." : "პასუხის ფორმა მოულოდნელია.");
          showModificationRetry();
          return;
        }
        state.modificationPage += 1;
        state.modificationTotal = total;
        addModifications(data);
        loaded = Object.keys(seenModifications).length;
        if (total === 0) {
          modificationStatus.textContent = "ამ თაობას მოდიფიკაცია არ აქვს.";
          setHelp(modificationHelp, "ამ თაობას მოდიფიკაცია არ აქვს.");
          modification.disabled = true;
          moreModifications.hidden = true;
          return;
        }
        modification.options[0].textContent = "აირჩიეთ მოდიფიკაცია";
        modification.disabled = false;
        setHelp(modificationHelp, "");
        moreModifications.textContent = "მეტი მოდიფიკაციის ჩატვირთვა";
        var done = loaded >= total || data.length === 0;
        modificationStatus.textContent = "ჩატვირთულია " + loaded + " / " + total;
        moreModifications.hidden = done;
        moreModifications.disabled = done;
      })
      .catch(function (error) {
        if (current !== state.modificationRequestId || (error && error.name === "AbortError")) return;
        modificationStatus.textContent = "ქსელის შეცდომა.";
        showModificationRetry();
      })
      .then(function () {
        if (current !== state.modificationRequestId) return;
        state.modificationLoading = false;
        if (!moreModifications.hidden) moreModifications.disabled = false;
      });
  }
  function loadDetail() {
    var modificationId = state.modification;
    if (!/^[1-9][0-9]{0,18}$/.test(modificationId)) {
      modificationStatus.textContent = messages[422];
      return;
    }
    var current = ++state.detailRequestId;
    if (state.detailAbort) state.detailAbort.abort();
    state.detailAbort = typeof AbortController === "function" ? new AbortController() : null;
    detail.hidden = true;
    while (detailList.firstChild) detailList.removeChild(detailList.firstChild);
    modificationStatus.textContent = "მოდიფიკაცია იტვირთება.";
    var body = new FormData();
    body.append("_csrf", form.querySelector("[name=_csrf]").value);
    body.append("operation", "/api/v1/modifications/{id}");
    body.append("id", modificationId);
    fetch(form.action, {method: "POST", body: body, headers: {Accept: "application/json"}, credentials: "same-origin", signal: state.detailAbort ? state.detailAbort.signal : undefined})
      .then(function (response) { return response.json().then(function (payload) { return {status: response.status, payload: payload}; }); })
      .then(function (result) {
        if (current !== state.detailRequestId || modification.value !== modificationId) return;
        var code = result.status;
        var item = (result.payload || {}).body || {};
        item = item.data || null;
        if (code !== 200 || !item || typeof item !== "object" || Array.isArray(item)) {
          modificationStatus.textContent = messages[code] || (code >= 500 ? "სერვერის შეცდომა." : "პასუხის ფორმა მოულოდნელია.");
          return;
        }
        detail.hidden = false;
        addDetailRow("სახელი", item.name);
        addDetailRow("ID", item.id);
        addDetailRow("ძრავი, სმ³", item.engine_volume_cc);
        addDetailRow("გადაცემათა კოლოფი", item.transmission);
        addDetailRow("წამყვანი", item.drive_type);
        addDetailRow("ძარა", item.body_type);
        addDetailRow("წლები", item.year_start == null ? "" : (item.year_end == null ? item.year_start + "–" : item.year_start + "–" + item.year_end));
        if (item.brand) addDetailRow("ბრენდი", item.brand.name);
        if (item.model) addDetailRow("მოდელი", item.model.name);
        if (item.generation) addDetailRow("თაობა", item.generation.name);
        if (item.generation) addDetailRow("თაობის ID", item.generation.id);
        modificationStatus.textContent = "მოდიფიკაცია არჩეულია.";
      })
      .catch(function (error) {
        if (current !== state.detailRequestId || (error && error.name === "AbortError")) return;
        modificationStatus.textContent = "ქსელის შეცდომა.";
      });
  }
  form.addEventListener("submit", function (event) { event.preventDefault(); });
  brand.addEventListener("change", function () {
    state.brand = brand.value;
    state.generation = "";
    state.modification = "";
    generation.value = "";
    modification.value = "";
    generation.disabled = true;
    modification.disabled = true;
    resetModels();
    if (state.brand !== "") loadModels();
  });
  model.addEventListener("change", function () {
    state.model = model.value;
    resetGenerations();
    if (state.model !== "") loadGenerations();
  });
  generation.addEventListener("change", function () {
    state.generation = generation.value;
    resetModifications();
    if (state.generation !== "") loadModifications();
  });
  modification.addEventListener("change", function () {
    state.modification = modification.value;
    clearDetail();
    if (state.modification !== "") loadDetail();
  });
  more.addEventListener("click", function () { load(); });
  moreModels.addEventListener("click", function () { loadModels(); });
  moreGenerations.addEventListener("click", function () { loadGenerations(); });
  moreModifications.addEventListener("click", function () { loadModifications(); });
  load();
})();
</script>
JS;
        self::desk(
            'ავტომობილების კატალოგი',
            $main,
            'explorer',
            $project,
            trim((string) $user['first_name'] . ' ' . (string) $user['last_name']),
            (string) $user['email']
        );
    }

    /** @param array<string, string> $errors */
    public static function confirm(string $title, string $action, string $token, string $button, array $errors): void
    {
        $error = isset($errors['form']) ? '<p class="err">' . self::e($errors['form']) . '</p>' : '';
        $fields = $error . '<input type="hidden" name="token" value="' . self::e($token) . '">';
        self::auth($title, '03', $title, self::e($title), '', $fields, $action, $button, [], '<p class="alt"><a href="/forgot-password">ახალი ბმული</a></p>', self::loginAside());
    }

    /** @param array<string, string> $errors */
    public static function forgot(array $errors, bool $sent = false): void
    {
        $note = $sent ? '<p class="ok">' . self::e((string) ($errors['form'] ?? '')) . '</p>' : '';
        $formError = !$sent && isset($errors['form']) ? '<p class="err">' . self::e($errors['form']) . '</p>' : '';
        $fields = $note . $formError . self::field('ელფოსტა', 'email', 'email', [], [], 'email');
        self::auth('პაროლის აღდგენა', '03', 'პაროლის აღდგენა', 'პაროლის <span>აღდგენა</span>', 'შეიყვანეთ ანგარიშთან დაკავშირებული ელფოსტის მისამართი. თუ ასეთი ანგარიში არსებობს, გამოგიგზავნით პაროლის აღდგენის ინსტრუქციას.', $fields, '/forgot-password', 'აღდგენის ბმულის გაგზავნა', [], '', self::loginAside());
    }

    /** @param array<string, string> $errors */
    public static function resetForm(string $token, array $errors): void
    {
        $error = isset($errors['form']) ? '<p class="err">' . self::e($errors['form']) . '</p>' : '';
        $fields = $error . '<input type="hidden" name="token" value="' . self::e($token) . '">'
            . self::field('პაროლი', 'password', 'password', [], $errors, 'new-password', 'პაროლი უნდა შეიცავდეს მინიმუმ 15 სიმბოლოს.')
            . self::field('გაიმეორეთ პაროლი', 'password_confirmation', 'password', [], $errors, 'new-password');
        self::auth('ახალი პაროლი', '03', 'ახალი პაროლი', 'ახალი <span>პაროლი</span>', 'შეიყვანეთ ახალი პაროლი. ძველი პაროლით შესვლა აღარ მოხდება.', $fields, '/reset-password', 'პაროლის შენახვა', [], '', self::loginAside());
    }

    /** @param array<string,mixed> $user @param array{period:string,stats:array<string,int>,users:list<array<string,string>>,projects:list<array<string,string>>} $overview */
    public static function admin(array $user, array $overview): void
    {
        self::adminPage($user, 'მიმოხილვა', 'overview', self::comingSoonControl() . self::photoReports() . self::adminStats($overview) . self::adminActivity($overview) . self::adminSystem($overview));
    }

    /**
     * @param array<string,mixed> $user
     * @param array{q:string,status:string,role:string,page:int,per:int,total:int,pages:int,rows:list<array{id:int,name:string,email:string,role:string,status:string,verified:bool,created_at:string,last_login_at:string,projects:int}>} $list
     */
    public static function adminUsers(array $user, array $list): void
    {
        self::adminPage($user, 'მომხმარებლები', 'users', self::adminUserList($list));
    }

    /**
     * @param array<string,mixed> $user
     * @param array{id:int,name:string,email:string,role:string,status:string,verified:bool,created_at:string,last_login_at:string,projects:list<array{id:int,name:string,status:string,created_at:string,used:int,limit:int,remaining:int}>} $detail
     */
    public static function adminUser(array $user, array $detail): void
    {
        self::adminPage($user, 'მომხმარებლები', 'users', self::adminUserDetail($detail));
    }

    /**
     * @param array<string,mixed> $user
     * @param array{q:string,status:string,page:int,per:int,total:int,pages:int,rows:list<array{id:int,name:string,owner_id:int,owner:string,email:string,status:string,created_at:string,used:int,limit:int,remaining:int}>} $list
     */
    public static function adminProjects(array $user, array $list): void
    {
        self::adminPage($user, 'პროექტები', 'projects', self::adminProjectList($list));
    }

    /**
     * @param array<string,mixed> $user
     * @param array{id:int,name:string,website:string,status:string,created_at:string,updated_at:string,owner_id:int,owner:string,email:string,owner_status:string,verified:bool,used:int,limit:int,remaining:int,per_minute:int,days:list<array{day:string,used:int}>,keys:list<array{id:int,masked:string,scope:string,created_at:string,expires_at:string,revoked_at:string,last_used_at:string,status:string}>} $detail
     */
    public static function adminProject(array $user, array $detail): void
    {
        self::adminPage($user, 'პროექტები', 'projects', self::adminProjectDetail($detail));
    }

    /** @param array<string,mixed> $user */
    private static function adminPage(array $user, string $heading, string $section, string $main): void
    {
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        $name = trim((string) $user['first_name'] . ' ' . (string) $user['last_name']);
        $email = (string) $user['email'];
        echo '<!doctype html><html lang="ka"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . self::e($heading) . ' — GeoAPI ადმინი</title>'
            . '<link rel="icon" href="/assets/brand/favicon-32-lime.svg" type="image/svg+xml">'
            . '<link rel="stylesheet" href="/assets/fonts/fonts.css?v=20261018">'
            . '<link rel="stylesheet" href="/assets/admin.css?v=20261020r"></head><body class="admin-app">';
        echo '<a class="admin-skip" href="#admin-content">შინაარსზე გადასვლა</a>';
        echo '<header class="admin-bar"><button class="admin-menu" type="button" aria-expanded="false" aria-controls="admin-nav">მენიუ</button>'
            . '<p class="admin-brand">GeoAPI <span>ადმინი</span></p><h1>' . self::e($heading) . '</h1>'
            . '<div class="admin-who"><p><b>' . self::e($name) . '</b><span>' . self::e($email) . '</span></p>'
            . '<a href="/account">დეველოპერის კაბინეტი</a>'
            . '<form method="post" action="/logout"><input type="hidden" name="_csrf" value="' . self::e(Session::csrf()) . '"><button type="submit">გასვლა</button></form></div></header>';
        echo '<div class="admin-shell"><aside class="admin-side" id="admin-nav"><nav aria-label="ადმინისტრირება">'
            . self::adminNav($section)
            . '</nav><p class="admin-soon">მართვის ფუნქციები დაემატება შემდეგ ეტაპებზე.</p></aside>';
        echo '<main class="admin-main" id="admin-content">' . $main . '</main></div>';
        echo '<script>(function(){var b=document.querySelector(".admin-menu"),n=document.getElementById("admin-nav");if(!b||!n)return;var set=function(open){n.classList.toggle("is-open",open);b.setAttribute("aria-expanded",open?"true":"false");};b.addEventListener("click",function(){set(!n.classList.contains("is-open"));});document.addEventListener("keydown",function(e){if(e.key==="Escape")set(false);});})();</script>';
        echo '</body></html>';
    }

    private static function adminNav(string $section): string
    {
        $links = [
            'overview' => ['მიმოხილვა', '/admin'],
            'photos' => ['არასწორი ფოტოები', '/admin#photo-reports'],
            'users' => ['მომხმარებლები', '/admin/users'],
            'projects' => ['პროექტები', '/admin/projects'],
            'blog' => ['ბლოგი', '/admin/blog'],
        ];
        $soon = ['API მოხმარება', 'API გასაღებები', 'გამოწერის გეგმები', 'გამოწერები', 'გადახდები', 'აუდიტის ჟურნალი'];
        $html = '';
        foreach ($links as $key => [$label, $href]) {
            $current = $section === $key ? ' aria-current="page"' : '';
            $html .= '<a href="' . self::e($href) . '"' . $current . '>' . self::e($label) . '</a>';
        }
        foreach ($soon as $label) {
            $html .= '<span aria-disabled="true">' . self::e($label) . '<small>მალე</small></span>';
        }

        return $html;
    }

    /** @param array{q:string,status:string,role:string,page:int,per:int,total:int,pages:int,rows:list<array{id:int,name:string,email:string,role:string,status:string,verified:bool,created_at:string,last_login_at:string,projects:int}>} $list */
    private static function adminUserList(array $list): string
    {
        $status = static function (string $value) use ($list): string {
            return $list['status'] === $value ? ' selected' : '';
        };
        $role = static function (string $value) use ($list): string {
            return $list['role'] === $value ? ' selected' : '';
        };
        $hiddenPer = $list['per'] === 20 ? '' : '<input type="hidden" name="per" value="' . $list['per'] . '">';
        $form = '<form class="admin-filters" method="get" action="/admin/users">' . $hiddenPer
            . '<label for="user-q">ძებნა<input id="user-q" name="q" type="search" maxlength="80" value="' . self::e($list['q']) . '"></label>'
            . '<label for="user-status">სტატუსი<select id="user-status" name="status">'
            . '<option value=""' . $status('') . '>ყველა</option>'
            . '<option value="active"' . $status('active') . '>აქტიური</option>'
            . '<option value="suspended"' . $status('suspended') . '>შეჩერებული</option></select></label>'
            . '<label for="user-role">როლი<select id="user-role" name="role">'
            . '<option value=""' . $role('') . '>ყველა</option>'
            . '<option value="admin"' . $role('admin') . '>ადმინი</option>'
            . '<option value="user"' . $role('user') . '>მომხმარებელი</option></select></label>'
            . '<button type="submit">ძებნა</button></form>';
        $rows = '';
        foreach ($list['rows'] as $row) {
            $login = $row['last_login_at'] === '' ? '—' : $row['last_login_at'];
            $rows .= '<tr data-user="' . $row['id'] . '"><td data-label="ID">' . $row['id'] . '</td><td data-label="სახელი"><a href="/admin/users/'
                . $row['id'] . '">' . self::e($row['name']) . '</a></td><td data-label="ელფოსტა">' . self::e($row['email'])
                . '</td><td data-label="როლი">' . self::e(self::adminRole($row['role'])) . '</td><td data-label="სტატუსი">'
                . self::e(self::adminStatus($row['status'])) . '</td><td data-label="ელფოსტის დადასტურება">'
                . self::e($row['verified'] ? 'დადასტურებული' : 'დაუდასტურებელი') . '</td><td data-label="რეგისტრაცია UTC">'
                . self::e($row['created_at']) . '</td><td data-label="ბოლო შესვლა UTC">' . self::e($login)
                . '</td><td data-label="პროექტები">' . $row['projects'] . '</td></tr>';
        }
        $body = $rows === ''
            ? '<p class="admin-empty" data-empty="users">მომხმარებელი ვერ მოიძებნა.</p>'
            : '<table class="admin-table"><thead><tr><th>ID</th><th>სახელი</th><th>ელფოსტა</th><th>როლი</th><th>სტატუსი</th><th>დადასტურება</th><th>რეგისტრაცია UTC</th><th>ბოლო შესვლა UTC</th><th>პროექტები</th></tr></thead><tbody>'
            . $rows . '</tbody></table>';
        $prev = $list['page'] > 1 ? '<a href="' . self::e(self::adminUserQuery($list, $list['page'] - 1)) . '">წინა</a>' : '';
        $next = $list['page'] < $list['pages'] ? '<a href="' . self::e(self::adminUserQuery($list, $list['page'] + 1)) . '">შემდეგი</a>' : '';

        return $form . '<p class="admin-meta" data-total="' . $list['total'] . '" data-page="' . $list['page'] . '">'
            . self::e((string) $list['total']) . ' მომხმარებელი · გვერდი ' . $list['page'] . ' / ' . $list['pages'] . '</p>'
            . $body . '<nav class="admin-pager" aria-label="გვერდები">' . $prev . $next . '</nav>';
    }

    /** @param array{q:string,status:string,role:string,page:int,per:int} $list */
    private static function adminUserQuery(array $list, int $page): string
    {
        $params = [];
        if ($list['q'] !== '') {
            $params['q'] = $list['q'];
        }
        if ($list['status'] !== '') {
            $params['status'] = $list['status'];
        }
        if ($list['role'] !== '') {
            $params['role'] = $list['role'];
        }
        if ($list['per'] !== 20) {
            $params['per'] = (string) $list['per'];
        }
        if ($page > 1) {
            $params['page'] = (string) $page;
        }
        $built = http_build_query($params);

        return '/admin/users' . ($built === '' ? '' : '?' . $built);
    }

    /** @param array{id:int,name:string,email:string,role:string,status:string,verified:bool,created_at:string,last_login_at:string,projects:list<array{id:int,name:string,status:string,created_at:string,used:int,limit:int,remaining:int}>} $detail */
    private static function adminUserDetail(array $detail): string
    {
        $login = $detail['last_login_at'] === '' ? '—' : $detail['last_login_at'];
        $account = '<section class="admin-system" data-user="' . $detail['id'] . '"><h2>ანგარიში</h2><dl>'
            . self::adminFact('ID', (string) $detail['id'])
            . self::adminFact('სახელი', $detail['name'])
            . self::adminFact('ელფოსტა', $detail['email'])
            . self::adminFact('როლი', self::adminRole($detail['role']))
            . self::adminFact('სტატუსი', self::adminStatus($detail['status']))
            . self::adminFact('ელფოსტის დადასტურება', $detail['verified'] ? 'დადასტურებული' : 'დაუდასტურებელი')
            . self::adminFact('რეგისტრაცია UTC', $detail['created_at'])
            . self::adminFact('ბოლო შესვლა UTC', $login)
            . '</dl></section>';
        $rows = '';
        foreach ($detail['projects'] as $project) {
            $rows .= '<tr data-project="' . $project['id'] . '" data-used="' . $project['used'] . '" data-limit="' . $project['limit'] . '" data-remaining="' . $project['remaining'] . '">'
                . '<td data-label="ID">' . $project['id'] . '</td><td data-label="პროექტი"><a href="/admin/projects/' . $project['id'] . '">' . self::e($project['name']) . '</a></td><td data-label="სტატუსი">'
                . self::e(self::adminStatus($project['status'])) . '</td><td data-label="შექმნა UTC">' . self::e($project['created_at'])
                . '</td><td data-label="თვის მოხმარება">' . self::e(number_format($project['used'], 0, '.', ' '))
                . '</td><td data-label="თვის ლიმიტი">' . self::e(number_format($project['limit'], 0, '.', ' '))
                . '</td><td data-label="დარჩენილი">' . self::e(number_format($project['remaining'], 0, '.', ' ')) . '</td></tr>';
        }
        $projects = $rows === ''
            ? '<p class="admin-empty" data-empty="projects">ამ მომხმარებელს პროექტი არ აქვს.</p>'
            : '<table class="admin-table"><thead><tr><th>ID</th><th>პროექტი</th><th>სტატუსი</th><th>შექმნა UTC</th><th>თვის მოხმარება</th><th>თვის ლიმიტი</th><th>დარჩენილი</th></tr></thead><tbody>' . $rows . '</tbody></table>';

        return '<a class="admin-back" href="/admin/users">მომხმარებლები</a>' . $account . '<section><h2>პროექტები</h2>' . $projects . '</section>';
    }

    private static function adminFact(string $label, string $value): string
    {
        return '<div><dt>' . self::e($label) . '</dt><dd>' . self::e($value) . '</dd></div>';
    }

    /** @param array{q:string,status:string,page:int,per:int,total:int,pages:int,rows:list<array{id:int,name:string,owner_id:int,owner:string,email:string,status:string,created_at:string,used:int,limit:int,remaining:int}>} $list */
    private static function adminProjectList(array $list): string
    {
        $selected = static function (string $value) use ($list): string {
            return $list['status'] === $value ? ' selected' : '';
        };
        $hiddenPer = $list['per'] === 20 ? '' : '<input type="hidden" name="per" value="' . $list['per'] . '">';
        $form = '<form class="admin-filters" method="get" action="/admin/projects">' . $hiddenPer
            . '<label for="project-q">ძებნა<input id="project-q" name="q" type="search" maxlength="80" value="' . self::e($list['q']) . '"></label>'
            . '<label for="project-status">სტატუსი<select id="project-status" name="status">'
            . '<option value=""' . $selected('') . '>ყველა</option>'
            . '<option value="active"' . $selected('active') . '>აქტიური</option>'
            . '<option value="disabled"' . $selected('disabled') . '>გამორთული</option></select></label>'
            . '<button type="submit">ძებნა</button></form>';
        $rows = '';
        foreach ($list['rows'] as $row) {
            $rows .= '<tr data-project="' . $row['id'] . '" data-used="' . $row['used'] . '" data-limit="' . $row['limit'] . '" data-remaining="' . $row['remaining'] . '">'
                . '<td data-label="ID">' . $row['id'] . '</td><td data-label="პროექტი"><a href="/admin/projects/' . $row['id'] . '">' . self::e($row['name']) . '</a></td>'
                . '<td data-label="მფლობელი"><a href="/admin/users/' . $row['owner_id'] . '">' . self::e($row['owner']) . '</a></td><td data-label="ელფოსტა">' . self::e($row['email'])
                . '</td><td data-label="სტატუსი">' . self::e(self::adminStatus($row['status'])) . '</td><td data-label="შექმნა UTC">' . self::e($row['created_at'])
                . '</td><td data-label="თვის მოხმარება">' . self::e(number_format($row['used'], 0, '.', ' '))
                . '</td><td data-label="თვის ლიმიტი">' . self::e(number_format($row['limit'], 0, '.', ' '))
                . '</td><td data-label="დარჩენილი">' . self::e(number_format($row['remaining'], 0, '.', ' ')) . '</td></tr>';
        }
        $body = $rows === ''
            ? '<p class="admin-empty" data-empty="projects">პროექტი ვერ მოიძებნა.</p>'
            : '<table class="admin-table"><thead><tr><th>ID</th><th>პროექტი</th><th>მფლობელი</th><th>ელფოსტა</th><th>სტატუსი</th><th>შექმნა UTC</th><th>თვის მოხმარება</th><th>თვის ლიმიტი</th><th>დარჩენილი</th></tr></thead><tbody>' . $rows . '</tbody></table>';
        $prev = $list['page'] > 1 ? '<a href="' . self::e(self::adminProjectQuery($list, $list['page'] - 1)) . '">წინა</a>' : '';
        $next = $list['page'] < $list['pages'] ? '<a href="' . self::e(self::adminProjectQuery($list, $list['page'] + 1)) . '">შემდეგი</a>' : '';

        return $form . '<p class="admin-meta" data-total="' . $list['total'] . '" data-page="' . $list['page'] . '">'
            . self::e((string) $list['total']) . ' პროექტი · გვერდი ' . $list['page'] . ' / ' . $list['pages'] . '</p>'
            . $body . '<nav class="admin-pager" aria-label="გვერდები">' . $prev . $next . '</nav>';
    }

    /** @param array{q:string,status:string,page:int,per:int} $list */
    private static function adminProjectQuery(array $list, int $page): string
    {
        $params = [];
        if ($list['q'] !== '') {
            $params['q'] = $list['q'];
        }
        if ($list['status'] !== '') {
            $params['status'] = $list['status'];
        }
        if ($list['per'] !== 20) {
            $params['per'] = (string) $list['per'];
        }
        if ($page > 1) {
            $params['page'] = (string) $page;
        }
        $built = http_build_query($params);

        return '/admin/projects' . ($built === '' ? '' : '?' . $built);
    }

    /** @param array{id:int,name:string,website:string,status:string,created_at:string,updated_at:string,owner_id:int,owner:string,email:string,owner_status:string,verified:bool,used:int,limit:int,remaining:int,per_minute:int,days:list<array{day:string,used:int}>,keys:list<array{id:int,masked:string,scope:string,created_at:string,expires_at:string,revoked_at:string,last_used_at:string,status:string}>} $detail */
    private static function adminProjectDetail(array $detail): string
    {
        $blank = static function (string $value): string {
            return $value === '' ? '—' : $value;
        };
        $project = '<section class="admin-system" data-project="' . $detail['id'] . '" data-used="' . $detail['used'] . '" data-limit="' . $detail['limit'] . '" data-remaining="' . $detail['remaining'] . '" data-minute="' . $detail['per_minute'] . '"><h2>პროექტი</h2><dl>'
            . self::adminFact('ID', (string) $detail['id'])
            . self::adminFact('სახელი', $detail['name'])
            . self::adminFact('საიტი', $blank($detail['website']))
            . self::adminFact('სტატუსი', self::adminStatus($detail['status']))
            . self::adminFact('შექმნა UTC', $detail['created_at'])
            . self::adminFact('განახლება UTC', $detail['updated_at'])
            . '</dl></section>';
        $owner = '<section class="admin-system"><h2>მფლობელი</h2><dl>'
            . self::adminFact('ID', (string) $detail['owner_id'])
            . '<div><dt>სახელი</dt><dd><a href="/admin/users/' . $detail['owner_id'] . '">' . self::e($detail['owner']) . '</a></dd></div>'
            . self::adminFact('ელფოსტა', $detail['email'])
            . self::adminFact('ანგარიშის სტატუსი', self::adminStatus($detail['owner_status']))
            . self::adminFact('ელფოსტის დადასტურება', $detail['verified'] ? 'დადასტურებული' : 'დაუდასტურებელი')
            . '</dl></section>';
        $usage = '<section class="admin-system"><h2>მოხმარება</h2><dl>'
            . self::adminFact('UTC თვის მოთხოვნები', number_format($detail['used'], 0, '.', ' '))
            . self::adminFact('თვის ლიმიტი', number_format($detail['limit'], 0, '.', ' '))
            . self::adminFact('დარჩენილი', number_format($detail['remaining'], 0, '.', ' '))
            . self::adminFact('წუთში', (string) $detail['per_minute'])
            . '</dl></section>';
        $days = '';
        foreach ($detail['days'] as $day) {
            $days .= '<tr data-day="' . self::e($day['day']) . '" data-day-used="' . $day['used'] . '"><td data-label="დღე UTC">' . self::e($day['day']) . '</td><td data-label="მოთხოვნები">' . $day['used'] . '</td></tr>';
        }
        $keys = '';
        foreach ($detail['keys'] as $key) {
            $keys .= '<tr data-key="' . $key['id'] . '" data-key-status="' . self::e($key['status']) . '"><td data-label="ID">' . $key['id'] . '</td><td data-label="გასაღები">' . self::e($key['masked'])
                . '</td><td data-label="ფარგლები">' . self::e($key['scope']) . '</td><td data-label="შექმნა UTC">' . self::e($key['created_at'])
                . '</td><td data-label="ვადა UTC">' . self::e($blank($key['expires_at'])) . '</td><td data-label="გაუქმება UTC">' . self::e($blank($key['revoked_at']))
                . '</td><td data-label="ბოლო გამოყენება UTC">' . self::e($blank($key['last_used_at'])) . '</td><td data-label="სტატუსი">' . self::e(self::adminKeyStatus($key['status'])) . '</td></tr>';
        }
        $keyTable = $keys === ''
            ? '<p class="admin-empty" data-empty="keys">ამ პროექტს API გასაღები არ აქვს.</p>'
            : '<table class="admin-table"><thead><tr><th>ID</th><th>გასაღები</th><th>ფარგლები</th><th>შექმნა UTC</th><th>ვადა UTC</th><th>გაუქმება UTC</th><th>ბოლო გამოყენება UTC</th><th>სტატუსი</th></tr></thead><tbody>' . $keys . '</tbody></table>';

        return '<a class="admin-back" href="/admin/projects">პროექტები</a>' . $project . $owner . $usage
            . '<section><h2>ბოლო 30 UTC დღე</h2><table class="admin-table"><thead><tr><th>დღე UTC</th><th>მოთხოვნები</th></tr></thead><tbody>' . $days . '</tbody></table></section>'
            . '<section><h2>API გასაღებები</h2>' . $keyTable . '</section>';
    }

    private static function adminKeyStatus(string $status): string
    {
        return match ($status) {
            'active' => 'აქტიური',
            'revoked' => 'გაუქმებული',
            'expired' => 'ვადაგასული',
            default => $status,
        };
    }

    private static function adminRole(string $role): string
    {
        return match ($role) {
            'admin' => 'ადმინი',
            'user' => 'მომხმარებელი',
            default => $role,
        };
    }

    private static function comingSoonControl(): string
    {
        $html = '<section class="admin-group" id="coming-soon"><h2>Coming Soon</h2>';
        if (! self::comingSoonOn()) {
            $note = function_exists('session') && session('coming_soon_off') === true
                ? 'Coming Soon გამორთულია. საჯარო საიტი ისევ იხსნება.'
                : 'Coming Soon გამორთულია.';

            return $html . '<p class="admin-empty">' . self::e($note) . '</p></section>';
        }

        return $html . '<form class="admin-filters" method="post" action="/admin/coming-soon"><input type="hidden" name="_token" value="'
            . self::e(csrf_token()) . '"><button type="submit">Coming Soon-ის გამორთვა</button></form></section>';
    }

    private static function comingSoonOn(): bool
    {
        try {
            $row = \App\Models\Setting::query()->find('coming_soon');
        } catch (\Throwable) {
            return false;
        }

        return $row instanceof \App\Models\Setting && $row->value === true;
    }

    private static function photoReports(): string
    {
        $rows = [];
        try {
            if (class_exists(\Illuminate\Support\Facades\Schema::class) && \Illuminate\Support\Facades\Schema::hasTable('notifications')) {
                $found = \Illuminate\Support\Facades\DB::table('notifications')
                    ->orderByDesc('created_at')
                    ->limit(80)
                    ->get(['data', 'created_at']);
                $seen = [];
                foreach ($found as $row) {
                    $data = json_decode((string) $row->data, true);
                    if (!is_array($data) || ($data['title'] ?? '') !== 'არასწორი ფოტო') {
                        continue;
                    }
                    $body = trim((string) ($data['body'] ?? ''));
                    if ($body === '' || isset($seen[$body])) {
                        continue;
                    }
                    $seen[$body] = true;
                    $rows[] = ['body' => $body, 'at' => (string) $row->created_at];
                    if (count($rows) >= 20) {
                        break;
                    }
                }
            }
        } catch (\Throwable) {
            $rows = [];
        }

        $html = '<section class="admin-group" id="photo-reports"><h2>არასწორი ფოტოები</h2>';
        if ($rows === []) {
            return $html . '<p class="admin-empty">შეტყობინება ჯერ არ არის.</p></section>';
        }
        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr><td data-label="ფოტო">' . self::e($row['body']) . '</td><td data-label="დრო">' . self::e($row['at']) . '</td></tr>';
        }

        return $html . '<table class="admin-table"><thead><tr><th>ფოტო</th><th>დრო</th></tr></thead><tbody>' . $body . '</tbody></table></section>';
    }

    /** @param array{period:string,stats:array<string,int>,users:list<array<string,string>>,projects:list<array<string,string>>} $overview */
    private static function adminStats(array $overview): string
    {
        $groups = [
            'მომხმარებლები' => [
                ['users_total', 'რეგისტრირებული'],
                ['users_active', 'აქტიური'],
                ['users_suspended', 'შეჩერებული'],
            ],
            'პროექტები' => [
                ['projects_total', 'სულ'],
                ['projects_active', 'აქტიური'],
                ['projects_disabled', 'გამორთული'],
            ],
            'API გასაღებები' => [
                ['keys_total', 'სულ'],
                ['keys_active', 'აქტიური'],
                ['keys_revoked', 'გაუქმებული'],
            ],
            'მოთხოვნები' => [
                ['month_requests', 'ამ UTC თვის წარმატებული მოთხოვნები'],
            ],
        ];
        $html = '';
        foreach ($groups as $title => $cards) {
            $html .= '<section class="admin-group"><h2>' . self::e($title) . '</h2><div class="admin-cards">';
            foreach ($cards as [$key, $label]) {
                $value = (int) ($overview['stats'][$key] ?? 0);
                $html .= '<article class="admin-card"><p>' . self::e($label) . '</p><strong data-stat="' . self::e($key) . '">'
                    . self::e(number_format($value, 0, '.', ' ')) . '</strong></article>';
            }
            $html .= '</div></section>';
        }

        return $html;
    }

    /** @param array{period:string,stats:array<string,int>,users:list<array<string,string>>,projects:list<array<string,string>>} $overview */
    private static function adminActivity(array $overview): string
    {
        return '<div class="admin-split"><section><h2>ბოლო რეგისტრაციები</h2>' . self::adminTable($overview['users'], 'users', 'რეგისტრაცია ჯერ არ არის.')
            . '</section><section><h2>ბოლო პროექტები</h2>' . self::adminTable($overview['projects'], 'projects', 'პროექტი ჯერ არ შექმნილა.') . '</section></div>';
    }

    /** @param list<array<string,string>> $rows */
    private static function adminTable(array $rows, string $kind, string $empty): string
    {
        if ($rows === []) {
            return '<p class="admin-empty" data-empty="' . self::e($kind) . '">' . self::e($empty) . '</p>';
        }
        $nameLabel = $kind === 'projects' ? 'პროექტი' : 'სახელი';
        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr><td data-label="' . self::e($nameLabel) . '">' . self::e($row['name']) . '</td><td data-label="ელფოსტა">'
                . self::e($row['email']) . '</td><td data-label="სტატუსი">' . self::e(self::adminStatus($row['status']))
                . '</td><td data-label="UTC">' . self::e($row['created_at']) . '</td></tr>';
        }

        return '<table class="admin-table"><thead><tr><th>' . self::e($nameLabel) . '</th><th>ელფოსტა</th><th>სტატუსი</th><th>UTC</th></tr></thead><tbody>'
            . $body . '</tbody></table>';
    }

    /** @param array{period:string,stats:array<string,int>,users:list<array<string,string>>,projects:list<array<string,string>>} $overview */
    private static function adminSystem(array $overview): string
    {
        $month = substr($overview['period'], 0, 7);

        return '<section class="admin-system"><h2>სისტემა</h2><dl><div><dt>UTC თვე</dt><dd>' . self::e($month)
            . '</dd></div><div><dt>აქტიური გასაღები</dt><dd>გაუქმებული და ვადაგასული არ ითვლება</dd></div>'
            . '<div><dt>მოთხოვნები</dt><dd>წარმატებული ერთეულები UTC კალენდარულ თვეში</dd></div></dl></section>';
    }

    private static function adminStatus(string $status): string
    {
        return match ($status) {
            'active' => 'აქტიური',
            'suspended' => 'შეჩერებული',
            'disabled' => 'გამორთული',
            default => $status,
        };
    }

    public static function notice(string $title, string $text): void
    {
        self::auth($title, '03', $title, self::e($title), '', '<p>' . self::e($text) . '</p>', '', '', [], '<p class="alt"><a href="/login">შესვლა</a></p>', self::loginAside());
    }

    public static function unavailable(): void
    {
        http_response_code(503);
        self::auth('სერვისი დროებით მიუწვდომელია', '—', 'შეცდომა', 'სერვისი დროებით <span>მიუწვდომელია</span>', '', '<p class="err">სერვერთან დაკავშირება ვერ მოხერხდა. სცადეთ მოგვიანებით.</p>', '', '', [], '', self::loginAside());
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private static function field(string $label, string $name, string $type, array $old, array $errors, string $autocomplete, string $hint = ''): string
    {
        $value = $type === 'password' ? '' : self::e((string) ($old[$name] ?? ''));
        $err = isset($errors[$name]) ? '<p class="hint err">' . self::e($errors[$name]) . '</p>' : '';
        $hintHtml = $hint !== '' ? '<p class="hint">' . self::e($hint) . '</p>' : '';
        $required = ' required';

        return '<div class="fld"><div class="lbl"><span>' . self::e($label) . '</span></div><input class="inp" type="' . $type . '" name="' . $name
            . '" value="' . $value . '"' . $required . ' autocomplete="' . self::e($autocomplete) . '" aria-label="' . self::e($label) . '">' . $hintHtml . $err . '</div>';
    }

    /** @param array<string, string> $errors */
    private static function auth(
        string $title,
        string $step,
        string $crumb,
        string $heading,
        string $sub,
        string $fields,
        string $action,
        string $button,
        array $errors,
        string $footer,
        string $aside
    ): void {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        $formError = isset($errors['form']) ? '<p class="err">' . self::e($errors['form']) . '</p>' : '';
        $submit = $button === '' ? '' : '<button class="btn btn-l sbm" type="submit">' . self::e($button) . ' ' . SiteChrome::icon('arrow') . '</button>';
        $form = $action === '' ? $fields : '<form method="post" action="' . self::e($action) . '" autocomplete="on"><input type="hidden" name="_csrf" value="'
            . self::e(Session::csrf()) . '">' . $formError . $fields . $submit . '</form>';
        echo SiteChrome::start($title);
        echo '<main class="au" id="content"><div class="l"><div class="top">' . SiteChrome::logo()
            . '<a class="btn btn-g" href="/" style="height:36px">მთავარი</a></div><div class="form"><div class="dl"><b>' . self::e($step) . '</b>' . self::e($crumb) . '</div><h1>'
            . $heading . '</h1>' . ($sub !== '' ? '<p class="sub">' . $sub . '</p>' : '') . $form . $footer . '</div><div class="foot"><span>© '
            . date('Y') . ' GeoAPI</span><a href="/terms">გამოყენების წესები</a><a href="/privacy">კონფიდენციალურობა</a>' . SiteChrome::themeButton() . '</div></div>'
            . $aside . '</main>';
        echo '<script src="/assets/site.js?v=20261018f"></script>';
        echo SiteChrome::end();
    }

    /** @param array<string, mixed>|null $project @param list<array<string, mixed>> $projects */
    private static function desk(string $title, string $main, string $active, ?array $project, string $who, string $email = '', array $projects = []): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        $nav = '';
        $tabs = '';
        $context = '';
        if ($project === null) {
            $context = '<p class="side-k">პროექტები</p>';
            foreach ($projects as $item) {
                $nav .= '<a href="/account/projects/' . (int) $item['id'] . '">' . self::e((string) $item['name']) . '</a>';
            }
        } else {
            $base = '/account/projects/' . (int) $project['id'];
            $context = '<a class="back" href="/account">ყველა პროექტი</a><p class="side-name">' . self::e((string) $project['name']) . '</p>';
            $items = [
                'overview' => [$base . '#overview', 'პროექტის პარამეტრები'],
                'keys' => [$base . '#keys', 'API გასაღებები'],
                'playground' => [$base . '/playground', 'Playground'],
                'explorer' => [$base . '/explorer', 'Vehicle Explorer'],
                'domains' => [$base . '#domains', 'დაშვებული დომენები'],
                'usage' => [$base . '#usage', 'მოხმარების სტატისტიკა'],
                'style' => [$base . '#style', 'ვიჯეტის ჩასმა'],
            ];
            foreach ($items as $key => [$href, $label]) {
                $on = $active === $key ? ' class="on"' : '';
                $nav .= '<a href="' . $href . '"' . $on . '>' . self::e($label) . '</a>';
                $tabs .= '<a href="' . $href . '"' . $on . '>' . self::e($label) . '</a>';
            }
        }
        echo SiteChrome::start($title);
        echo '<div class="db" id="content"><aside class="sb"><div class="lg">' . SiteChrome::logo('/account') . '</div>'
            . $context . '<nav>' . $nav . '</nav><div class="bt"><div class="who"><b>' . self::e($who) . '</b><span>' . self::e($email) . '</span></div>'
            . '<a href="/guide">დოკუმენტაცია</a><div class="bt-row"><form method="post" action="/logout"><input type="hidden" name="_csrf" value="'
            . self::e(Session::csrf()) . '"><button type="submit">გასვლა</button></form>' . SiteChrome::themeButton()
            . '</div></div></aside><main><div class="mtop">' . SiteChrome::logo('/account')
            . '<a class="btn btn-g" href="/account">პროექტები</a></div>' . ($tabs === '' ? '' : '<div class="mtabs">' . $tabs . '</div>')
            . '<div class="mn">' . $main . '</div></main></div>';
        echo '<script src="/assets/site.js?v=20261018f"></script>';
        echo SiteChrome::end();
    }

    /** @param array<string, mixed>|null $widget @param array<string, string> $errors */
    private static function domainBlock(int $projectId, ?array $widget, array $errors): string
    {
        if ($widget === null) {
            return '<p class="hint">ვიჯეტი ამ პროექტზე ჯერ არ არის ჩართული.</p>';
        }
        $lines = '';
        foreach ($widget['allowed_origins'] as $origin) {
            $lines .= '<div class="dm"><span>' . self::e((string) $origin) . '</span><span class="hint">დაშვებული</span></div>';
        }
        if ($lines === '') {
            $lines = '<p class="hint">დაშვებული დომენი ჯერ არ არის. ჩაწერეთ სრული origin, თითო ხაზზე, მაგალითად https://example.com.</p>';
        }
        $originError = isset($errors['origins']) ? '<p class="hint err">' . self::e($errors['origins']) . '</p>' : '';
        $origins = implode("\n", $widget['allowed_origins']);
        $toggle = $widget['enabled'] ? '0' : '1';
        $toggleLabel = $widget['enabled'] ? 'გამორთვა' : 'ჩართვა';

        return $lines
            . '<form method="post" action="/account/projects/' . $projectId . '"><input type="hidden" name="_csrf" value="' . self::e(Session::csrf())
            . '"><input type="hidden" name="action" value="save_origins"><div class="fld"><div class="lbl"><span>საიტის მისამართი</span></div><textarea class="inp" name="allowed_origins" aria-label="დაშვებული origin-ები">'
            . self::e($origins) . '</textarea></div><p class="hint">შენახვა ცვლის სრულ სიას. http დასაშვებია მხოლოდ localhost-ისა და 127.0.0.1-ისთვის.</p>' . $originError . '<button class="btn btn-g" type="submit">ცვლილებების შენახვა</button></form>'
            . '<form method="post" action="/account/projects/' . $projectId . '" style="margin-top:12px"><input type="hidden" name="_csrf" value="' . self::e(Session::csrf())
            . '"><input type="hidden" name="action" value="set_widget"><input type="hidden" name="enabled" value="' . $toggle
            . '"><button class="btn btn-g" type="submit">' . self::e($toggleLabel) . '</button></form>'
            . '<form method="post" action="/account/projects/' . $projectId . '" style="margin-top:12px"><input type="hidden" name="_csrf" value="' . self::e(Session::csrf())
            . '"><input type="hidden" name="action" value="rotate_widget"><button class="btn btn-g" type="submit">საჯარო გასაღების შეცვლა</button></form>'
            . '<p class="hint">საჯარო API გასაღების შეცვლის შემდეგ ძველი გასაღები აღარ იმუშავებს. ვიჯეტის ჩართვა ან გამორთვა ახალ გასაღებს არ ქმნის.</p>';
    }

    /** @param array<string, mixed>|null $widget */
    private static function embedBlock(int $projectId, ?array $widget): string
    {
        if ($widget === null) {
            return '';
        }
        $key = (string) $widget['public_key'];
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = $_SERVER['HTTP_HOST'] ?? 'geoapi.ge';
        $base = ($https ? 'https' : 'http') . '://' . $host;
        $snippet = '<script src="' . $base . '/widget/v1/geoapi-filter.js"></script>' . "\n" . '<geoapi-filter public-key="' . $key . '"></geoapi-filter>';

        return '<textarea class="inp mono" readonly id="embed-code">' . self::e($snippet) . '</textarea>'
            . '<button class="btn btn-g" type="button" id="copy-embed" data-copy="embed-code" style="margin-top:12px">კოდის კოპირება</button>'
            . '<p class="hint">ჩაანაცვლება არ სჭირდება: public-key უკვე ამ პროექტის საჯარო API გასაღებია. <a href="/account/projects/' . $projectId . '/preview">წინასწარი ნახვა</a> იმავე თვიურ მოთხოვნების ლიმიტს იყენებს.</p>';
    }

    private static function keyRow(string $name, string $type, string $masked, string $when, string $state, string $copy, ?int $revokeId): string
    {
        $pill = str_starts_with($type, 'საჯარო') ? 'pill l' : 'pill';
        $dot = $state === 'აქტიური' ? 'dot' : 'dot off';
        $menu = $revokeId === null ? '' : '<form method="post" action="' . self::e(strtok($_SERVER['REQUEST_URI'] ?? '/account', '?') ?: '/account') . '"><input type="hidden" name="_csrf" value="'
            . self::e(Session::csrf()) . '"><input type="hidden" name="action" value="revoke_key"><input type="hidden" name="key_id" value="' . $revokeId
            . '"><button class="btn btn-g" type="submit">გასაღების გაუქმება</button></form>';

        return '<tr><td><b>' . self::e($name) . '</b></td><td><span class="' . $pill . '">' . self::e($type) . '</span></td><td><span class="key">'
            . self::e($masked) . '<button type="button" aria-label="გასაღების კოპირება" data-copy-text="' . self::e($copy) . '">' . SiteChrome::icon('copy') . '</button></span></td><td>'
            . self::e($when) . '</td><td><span class="' . $dot . '">' . self::e($state) . '</span></td><td>' . $menu . '</td></tr>';
    }

    private static function keyCard(string $name, string $type, string $masked, string $when, string $state, string $copy): string
    {
        $pill = str_starts_with($type, 'საჯარო') ? 'pill l' : 'pill';
        $dot = $state === 'აქტიური' ? 'dot' : 'dot off';

        return '<div><div class="r1"><b>' . self::e($name) . '</b><span class="' . $pill . '">' . self::e($type) . '</span></div><span class="key">'
            . self::e($masked) . '</span><div class="r2"><span>' . self::e($when) . '</span><span class="' . $dot . '">' . self::e($state) . '</span></div></div>';
    }

    /** @param array<string, mixed> $old @param array<string, string> $errors */
    private static function projectForm(string $action, string $name, string $button, array $old, array $errors, ?int $id): string
    {
        $hidden = $id === null ? '' : '<input type="hidden" name="project_id" value="' . $id . '">';
        $webError = isset($errors['website_url']) ? '<p class="hint err">' . self::e($errors['website_url']) . '</p>' : '';
        $nameError = isset($errors['name']) && $name === 'create_project' ? '<p class="hint err">' . self::e($errors['name']) . '</p>' : '';

        return '<form method="post" action="' . self::e($action) . '"><input type="hidden" name="_csrf" value="' . self::e(Session::csrf())
            . '"><input type="hidden" name="action" value="' . self::e($name) . '">' . $hidden
            . '<div class="fld"><div class="lbl"><span>პროექტის სახელი</span></div><input class="inp" name="name" value="' . self::e((string) ($old['name'] ?? '')) . '" required aria-label="პროექტის სახელი"></div>' . $nameError
            . '<div class="fld"><div class="lbl"><span>საიტის მისამართი</span></div><input class="inp" name="website_url" value="' . self::e((string) ($old['website_url'] ?? '')) . '" placeholder="https://example.com" aria-label="საიტის მისამართი"></div>' . $webError
            . '<button class="btn btn-l" type="submit" style="margin-top:16px">' . self::e($button) . '</button></form>';
    }

    private static function statusForm(int $id, string $current): string
    {
        $status = $current === 'active' ? 'disabled' : 'active';
        $label = $status === 'disabled' ? 'გამორთვა' : 'ჩართვა';

        return '<form method="post" action="/account" style="margin-top:12px"><input type="hidden" name="_csrf" value="' . self::e(Session::csrf())
            . '"><input type="hidden" name="action" value="set_project_status"><input type="hidden" name="project_id" value="' . $id
            . '"><input type="hidden" name="status" value="' . self::e($status) . '"><button class="btn btn-g" type="submit">' . self::e($label) . '</button></form>';
    }

    /** @param array<string, mixed> $old @param array<string, string> $errors */
    private static function keyForm(int $projectId, array $old, array $errors): string
    {
        $nameError = isset($errors['name']) ? '<p class="hint err">' . self::e($errors['name']) . '</p>' : '';
        $expError = isset($errors['expires_at']) ? '<p class="hint err">' . self::e($errors['expires_at']) . '</p>' : '';

        return '<form id="key-form" method="post" action="/account/projects/' . $projectId . '" style="margin-top:20px">'
            . '<input type="hidden" name="_csrf" value="' . self::e(Session::csrf()) . '"><input type="hidden" name="action" value="create_key">'
            . '<div class="fld"><div class="lbl"><span>გასაღების სახელი</span></div><input class="inp" name="name" value="' . self::e((string) ($old['name'] ?? '')) . '" required></div>' . $nameError
            . '<div class="fld"><div class="lbl"><span>ვადა</span></div><input class="inp" name="expires_at" value="' . self::e((string) ($old['expires_at'] ?? '')) . '" placeholder="არასავალდებულო, მომავალი თარიღი" aria-label="გასაღების ვადა"></div>' . $expError
            . '<p class="hint">ცარიელი ველი ნიშნავს, რომ გასაღებს ვადა არ აქვს. ეს ფორმა ქმნის საიდუმლო API გასაღებს სერვერული მოთხოვნებისთვის.</p>'
            . '<button class="btn btn-l" type="submit" style="margin-top:16px">ახალი გასაღების შექმნა</button></form>';
    }

    private static function loginAside(): string
    {
        $counts = self::countStrip();

        return '<div class="r"><div class="dl"><b>GET</b><span class="mono">/api/v1/brands</span></div><h2>ბრენდები, მოდელები და <span>თაობები</span></h2>'
            . '<div class="codeb"><div class="tb"><div class="tabs"><button type="button" class="on">პასუხის მაგალითი</button></div><span class="pill ok">200</span></div>'
            . '<pre class="json">{
  "data": [{
    "id": "1",
    "slug": "toyota",
    "name": "Toyota",
    "synonyms": [],
    "is_lcv_or_commercial": false,
    "logo_url_light": null,
    "logo_url_dark": null
  }],
  "meta": { "page": 1, "per_page": 50, "total": 1 }
}</pre></div><p class="hint">ქვემოთ ნაჩვენებია API-ის პასუხის მაგალითი. მონაცემები წარმოდგენილია განმარტების მიზნით.</p>' . $counts . '</div>';
    }

    private static function registerAside(): string
    {
        return '<div class="r"><div class="dl"><b>GET</b><span class="mono">/api/v1/brands</span></div><h2>სამი ნაბიჯი <span>ინტეგრაციამდე</span></h2>'
            . '<div class="steps"><div><code>01</code><b>შექმენით ანგარიში</b><p>დარეგისტრირდით და დაადასტურეთ ელფოსტის მისამართი.</p></div>'
            . '<div><code>02</code><b>შექმენით პროექტი და API გასაღები</b><p>აირჩიეთ საჯარო გასაღები ვიჯეტისთვის ან საიდუმლო გასაღები სერვერული API-ისთვის.</p></div>'
            . '<div><code>03</code><b>დაიწყეთ ინტეგრაცია</b><p>დაამატეთ ვიჯეტი თქვენს ვებსაიტზე ან გამოიძახეთ API თქვენი სერვერიდან.</p></div></div>'
            . self::countStrip() . '</div>';
    }

    private static function countStrip(): string
    {
        $counts = \Carapi\Catalog\PublicCounts::read();
        if (($counts['status'] ?? '') !== 'ok') {
            return '';
        }

        return '<div class="tot"><div><b class="num">' . self::num((int) $counts['brands']) . '</b><span>ბრენდი</span></div>'
            . '<div><b class="num">' . self::num((int) $counts['models']) . '</b><span>მოდელი</span></div>'
            . '<div><b class="num">' . self::num((int) $counts['generations']) . '</b><span>თაობა</span></div></div>';
    }

    private static function num(int $value): string
    {
        return number_format($value, 0, '.', "\u{00A0}");
    }

    /** @param array{limit:int,used:int,remaining:int,reset_at:string,per_minute:int,days:list<array{day:string,used:int}>,routes:list<array{route:string,used:int}>}|null $usage */
    private static function usageReport(?array $usage): string
    {
        if ($usage === null) {
            return '';
        }
        $max = 0;
        foreach ($usage['days'] as $day) {
            $max = max($max, $day['used']);
        }
        $bars = '';
        $rows = '';
        foreach ($usage['days'] as $day) {
            $height = $max > 0 ? (int) round(($day['used'] / $max) * 100) : 0;
            $bars .= '<span class="usage-col"><span class="usage-bar" style="height:' . $height . '%"><span class="usage-val">'
                . self::e($day['day'] . ', ' . $day['used']) . '</span></span></span>';
            $rows .= '<tr data-day="' . self::e($day['day']) . '"><td>' . self::e($day['day']) . '</td><td>' . $day['used'] . '</td></tr>';
        }
        if ($usage['routes'] === []) {
            $routes = '<p class="hint" id="usage-empty">ამ 30 დღეში წარმატებული მოთხოვნა არ არის.</p>';
        } else {
            $routes = '';
            foreach ($usage['routes'] as $route) {
                $routes .= '<tr><td class="mono">' . self::e($route['route']) . '</td><td>' . $route['used'] . '</td></tr>';
            }
            $routes = '<table class="usage-routes"><caption>მარშრუტები, ბოლო 30 UTC დღე</caption><thead><tr><th>მარშრუტი</th><th>წარმატებული</th></tr></thead><tbody>' . $routes . '</tbody></table>';
        }

        return '<p id="usage-minute">წუთში ' . $usage['per_minute'] . ' მოთხოვნა.</p>'
            . '<div class="usage-chart" id="usage-chart" role="img" aria-label="ბოლო 30 UTC დღის წარმატებული მოთხოვნები">' . $bars . '</div>'
            . '<table class="usage-days"><caption>ბოლო 30 UTC დღე</caption><thead><tr><th>დღე (UTC)</th><th>წარმატებული</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . $routes;
    }

    private static function day(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return $value;
        }
        $months = [1 => 'იან', 2 => 'თებ', 3 => 'მარ', 4 => 'აპრ', 5 => 'მაი', 6 => 'ივნ', 7 => 'ივლ', 8 => 'აგვ', 9 => 'სექ', 10 => 'ოქტ', 11 => 'ნოე', 12 => 'დეკ'];

        return date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . '. ' . date('Y', $ts);
    }

    private static function ago(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return $value;
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'ახლახან';
        }
        if ($diff < 3600) {
            return (int) floor($diff / 60) . ' წუთის წინ';
        }
        if ($diff < 86400) {
            return (int) floor($diff / 3600) . ' საათის წინ';
        }

        return (int) floor($diff / 86400) . ' დღის წინ';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
