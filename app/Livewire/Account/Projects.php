<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Access\LegacyGate;
use App\Billing\V2\Flag;
use App\Billing\V2\Gate;
use App\Billing\V2\ProjectLimitReached;
use App\Billing\V2\Workspace;
use Geoapi\Platform\ApiKeys;
use Geoapi\Platform\Connection;
use Geoapi\Platform\ProjectRepository;
use Geoapi\Platform\UnavailableException;
use Geoapi\Platform\WidgetRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use PDO;
use PDOException;

#[Layout('components.layouts.cabinet')]
#[Title('GeoAPI')]
final class Projects extends Component
{
    public string $name = '';

    public string $website_url = '';

    public string $key_name = '';

    public string $key_kind = 'secret';

    public ?int $projectId = null;

    public string $capInput = '';

    public function mount(): void
    {
        $snap = $this->snapshot();
        $this->projectId = $snap['project']['id'] ?? null;
    }

    public function create(): void
    {
        $legacyId = $this->legacyId();
        $name = trim($this->name);
        $website = trim($this->website_url);
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ]);
        if ($website !== '') {
            $parts = parse_url($website);
            $scheme = is_array($parts) ? (string) ($parts['scheme'] ?? '') : '';
            if (! is_array($parts) || ! in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass']) || empty($parts['host'])) {
                $this->addError('website_url', 'შეიყვანეთ საიტის მისამართი http ან https პროტოკოლით.');

                return;
            }
        }
        try {
            $id = (new ProjectRepository(Connection::pdo()))->insert($legacyId, $name, $website === '' ? null : $website);
        } catch (ProjectLimitReached $e) {
            $this->addError('name', $e->getMessage());

            return;
        }
        $this->projectId = $id;
        $this->reset('name', 'website_url');
        $this->dispatch('screen', name: 'projects');
    }

    public function selectProject(int $id): void
    {
        $owned = (new ProjectRepository(Connection::pdo()))->findOwned($this->legacyId(), $id);
        if ($owned === null) {
            return;
        }
        $this->projectId = $id;
    }

    public function createKey(): void
    {
        $this->validate([
            'key_name' => ['required', 'string', 'min:2', 'max:80'],
            'key_kind' => ['required', 'in:public,secret'],
        ]);
        if ($this->projectId === null) {
            $this->addError('key_name', app()->getLocale() === 'en' ? 'Create a project first.' : 'ჯერ შექმენით პროექტი.');

            return;
        }
        $userId = $this->legacyId();
        $pdo = Connection::pdo();
        if ($this->key_kind === 'public') {
            $token = (new WidgetRepository($pdo))->rotate($userId, (int) $this->projectId);
            if (! is_string($token) || $token === '') {
                $this->addError('key_name', app()->getLocale() === 'en' ? 'The public key was not created.' : 'საჯარო გასაღები ვერ შეიქმნა.');

                return;
            }
            session()->flash('revealed_key', $token);
            $this->reset('key_name');

            return;
        }
        if (auth()->user()?->email_verified_at === null) {
            $this->addError('key_name', app()->getLocale() === 'en'
                ? 'Confirm your email before creating a secret key.'
                : 'საიდუმლო API გასაღების შესაქმნელად ჯერ დაადასტურეთ ელფოსტა.');

            return;
        }
        if (class_exists(LegacyGate::class)) {
            $denial = LegacyGate::issueKey($userId);
            if ($denial !== null) {
                $this->addError('key_name', (string) ($denial['body']['error']['message'] ?? ''));

                return;
            }
        }
        $issued = ApiKeys::issue();
        $saved = (new ProjectRepository($pdo))->insertKey($userId, (int) $this->projectId, trim($this->key_name), $issued['selector'], $issued['hash'], null);
        if (! $saved) {
            $this->addError('key_name', app()->getLocale() === 'en' ? 'The key was not created.' : 'გასაღები ვერ შეიქმნა.');

            return;
        }
        session()->flash('revealed_key', $issued['token']);
        $this->reset('key_name');
    }

    public function setCap(): void
    {
        if (! Flag::on() || $this->projectId === null) {
            return;
        }
        $raw = trim($this->capInput);
        if ($raw !== '' && preg_match('/^[1-9][0-9]{0,8}$/', $raw) !== 1) {
            $this->addError('capInput', app()->getLocale() === 'en' ? 'Enter a whole number, or leave it empty to clear the cap.' : 'ჩაწერეთ მთელი რიცხვი, ან დატოვეთ ცარიელი ზღვრის მოსახსნელად.');

            return;
        }
        $ok = Gate::setCap($this->legacyId(), (int) $this->projectId, $raw === '' ? null : (int) $raw);
        if (! $ok) {
            $this->addError('capInput', app()->getLocale() === 'en' ? 'That cap was not saved.' : 'ზღვარი ვერ შეინახა.');

            return;
        }
        session()->flash('billing', app()->getLocale() === 'en' ? 'The project cap was saved.' : 'პროექტის ზღვარი შეინახა.');
    }

    public function revokeKey(int $keyId): void
    {
        if ($this->projectId === null) {
            return;
        }
        (new ProjectRepository(Connection::pdo()))->revoke($this->legacyId(), (int) $this->projectId, $keyId);
    }

    public function render()
    {
        return view('livewire.account.projects', $this->snapshot());
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $emptyDays = [];
        for ($i = 0; $i < 30; $i++) {
            $emptyDays[] = ['day' => '', 'used' => 0];
        }
        $base = [
            'unavailable' => false,
            'demo' => false,
            'projects' => [],
            'project' => null,
            'keys' => [],
            'widget' => null,
            'used' => 0,
            'limit' => 10000,
            'remaining' => 10000,
            'perMinute' => 60,
            'unlimited' => false,
            'days' => $emptyDays,
            'activeKeys' => 0,
            'cars' => [],
            'usageById' => [],
            'v2' => null,
        ];
        if (filter_var(env('GEOAPI_CABINET_DEMO', false), FILTER_VALIDATE_BOOL)) {
            return $this->demoSnapshot($base);
        }
        try {
            $pdo = Connection::pdo();
            $repo = new ProjectRepository($pdo);
            $projects = $repo->forUser($this->legacyId());
        } catch (UnavailableException|PDOException) {
            $base['unavailable'] = true;

            return $base;
        }
        $base['projects'] = $projects;
        foreach ($projects as $row) {
            try {
                $base['usageById'][(int) $row['id']] = $this->usage($pdo, (int) $row['id']);
            } catch (PDOException) {
            }
        }
        $project = null;
        foreach ($projects as $row) {
            if ($this->projectId !== null && (int) $row['id'] === (int) $this->projectId) {
                $project = $row;
                break;
            }
        }
        if ($project === null && $projects !== []) {
            $project = $projects[0];
            $this->projectId = (int) $project['id'];
        }
        if ($project === null) {
            return $base;
        }
        $base['project'] = $project;
        $id = (int) $project['id'];
        $usage = $this->usage($pdo, $id);
        $base['used'] = $usage['used'];
        $base['limit'] = $usage['limit'];
        $base['remaining'] = max(0, $usage['limit'] - $usage['used']);
        $base['perMinute'] = $usage['per_minute'];
        $base['unlimited'] = $usage['unlimited'];
        $base['days'] = $this->days($pdo, $id);
        $keys = $repo->keys($this->legacyId(), $id);
        $base['keys'] = $keys;
        $active = 0;
        foreach ($keys as $key) {
            if (($key['revoked_at'] ?? null) === null) {
                $active++;
            }
        }
        $widget = (new WidgetRepository($pdo))->findOwned($this->legacyId(), $id);
        if (is_array($widget) && ($widget['public_key'] ?? '') !== '') {
            $active++;
        }
        $base['widget'] = $widget;
        $base['activeKeys'] = $active;
        if (Flag::on()) {
            try {
                $v2 = Workspace::present($this->legacyId());
            } catch (\Throwable) {
                $v2 = null;
            }
            if (is_array($v2) && ! empty($v2['shared'])) {
                $base['v2'] = $v2;
                $base['used'] = (int) $v2['used'];
                $base['limit'] = (int) $v2['quota'];
                $base['remaining'] = (int) $v2['remaining'];
                $base['perMinute'] = (int) $v2['rpm'];
                $base['unlimited'] = false;
                foreach ($v2['projects'] as $pid => $row) {
                    $base['usageById'][(int) $pid] = [
                        'used' => (int) $row['used'],
                        'limit' => $row['cap'] ?? (int) $v2['quota'],
                        'per_minute' => (int) $v2['rpm'],
                        'unlimited' => false,
                    ];
                }
            }
        }

        return $base;
    }

    /** @return array{used:int,limit:int,per_minute:int,unlimited:bool} */
    private function usage(PDO $pdo, int $projectId): array
    {
        try {
            $row = $this->usageRow($pdo, $projectId, true);
        } catch (PDOException $e) {
            if (! str_contains($e->getMessage(), 'plan_code') && ! str_contains($e->getMessage(), 'unlimited_month') && ! str_contains($e->getMessage(), 'plan_ends_at')) {
                throw $e;
            }
            $row = $this->usageRow($pdo, $projectId, false);
        }
        if ($row === null) {
            return ['used' => 0, 'limit' => 10000, 'per_minute' => 60, 'unlimited' => false];
        }
        $plan = isset($row['plan_code']) && $row['plan_code'] !== null && $row['plan_code'] !== '' ? (string) $row['plan_code'] : null;
        $ends = $row['plan_ends_at'] ?? null;
        $expired = is_string($ends) && $ends !== '' && strtotime($ends) !== false && strtotime($ends) <= time();
        $paid = $plan !== null && ! in_array($plan, ['free', 'cars-included'], true);
        $unlimited = isset($row['unlimited_month']) && in_array($row['unlimited_month'], [1, '1', true, 't', 'true'], true);
        $limit = (int) $row['monthly_quota'];
        if ($paid && $expired) {
            $limit = 100;
            $unlimited = false;
        }

        return [
            'used' => (int) $row['used_count'],
            'limit' => $limit,
            'per_minute' => (int) $row['per_minute'],
            'unlimited' => $unlimited && ! ($paid && $expired),
        ];
    }

    /** @return array<string, mixed>|null */
    private function usageRow(PDO $pdo, int $projectId, bool $planColumns): ?array
    {
        $extra = $planColumns ? ",\n                   l.plan_code AS plan_code,\n                   l.plan_ends_at AS plan_ends_at,\n                   l.unlimited_month AS unlimited_month" : '';
        $stmt = $pdo->prepare(<<<SQL
            SELECT COALESCE(l.monthly_quota, 10000) AS monthly_quota,
                   COALESCE(l.per_minute, 60) AS per_minute,
                   COALESCE(m.used_count, 0) AS used_count{$extra}
            FROM platform.projects p
            LEFT JOIN platform.project_limits l ON l.project_id = p.id
            LEFT JOIN platform.monthly_usage m ON m.project_id = p.id AND m.period = :period
            WHERE p.id = :id AND p.user_id = :user_id
        SQL);
        $stmt->bindValue('period', gmdate('Y-m-01'));
        $stmt->bindValue('id', $projectId, PDO::PARAM_INT);
        $stmt->bindValue('user_id', $this->legacyId(), PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return list<array{day:string,used:int}> */
    private function days(PDO $pdo, int $projectId): array
    {
        $today = gmmktime(0, 0, 0, (int) gmdate('n'), (int) gmdate('j'), (int) gmdate('Y'));
        $start = $today - (29 * 86400);
        $day = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'day' : 'day::text';
        $stmt = $pdo->prepare(
            'SELECT '.$day.' AS day, sum(used_count) AS used
            FROM platform.daily_usage
            WHERE project_id = :id AND day >= :start AND day <= :end
            GROUP BY day'
        );
        $stmt->bindValue('id', $projectId, PDO::PARAM_INT);
        $stmt->bindValue('start', gmdate('Y-m-d', $start));
        $stmt->bindValue('end', gmdate('Y-m-d', $today));
        $stmt->execute();
        $byDay = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $byDay[(string) $row['day']] = (int) $row['used'];
        }
        $days = [];
        for ($i = 0; $i < 30; $i++) {
            $day = gmdate('Y-m-d', $start + ($i * 86400));
            $days[] = ['day' => $day, 'used' => $byDay[$day] ?? 0];
        }

        return $days;
    }

    /** @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    private function demoSnapshot(array $base): array
    {
        $values = [42, 38, 55, 61, 48, 22, 18, 70, 66, 80, 74, 59, 40, 28, 90, 84, 77, 69, 51, 33, 26, 96, 88, 73, 64, 58, 41, 30, 86, 79];
        $days = [];
        foreach ($values as $i => $used) {
            $days[] = ['day' => gmdate('Y-m-d', time() - (29 - $i) * 86400), 'used' => $used];
        }
        $base['demo'] = true;
        $base['project'] = ['id' => 1, 'name' => 'დემო პროექტი'];
        $base['projects'] = [$base['project']];
        $base['usageById'] = [1 => ['used' => 2140, 'limit' => 10000, 'per_minute' => 60, 'unlimited' => false]];
        $base['used'] = 2140;
        $base['limit'] = 10000;
        $base['remaining'] = 7860;
        $base['perMinute'] = 60;
        $base['days'] = $days;
        $base['activeKeys'] = 2;
        $base['widget'] = ['public_key' => 'geoapi_pk_demo4f2a9c10'];
        $base['keys'] = [[
            'id' => 1,
            'name' => 'სერვერი',
            'key_selector' => 'demo91ab77e4',
            'revoked_at' => null,
            'demo_value' => 'geoapi_sk_demo91ab77e4',
        ]];
        $base['cars'] = [
            ['brand' => 'Toyota', 'model' => 'Camry', 'years' => '2017–2024', 'body' => 'სედანი', 'engine' => '2.5 ბენზინი', 'power' => '203 ცხ.ძ.', 'slug' => 'toyota', 'id' => '100'],
            ['brand' => 'Toyota', 'model' => 'Corolla', 'years' => '2018–2024', 'body' => 'სედანი', 'engine' => '1.8 ბენზინი', 'power' => '140 ცხ.ძ.', 'slug' => 'toyota', 'id' => '110'],
            ['brand' => 'BMW', 'model' => '3 Series', 'years' => '2019–2024', 'body' => 'სედანი', 'engine' => '2.0 ბენზინი', 'power' => '184 ცხ.ძ.', 'slug' => 'bmw', 'id' => '200'],
            ['brand' => 'BMW', 'model' => 'X5', 'years' => '2018–2024', 'body' => 'ჯიპი', 'engine' => '3.0 ბენზინი', 'power' => '340 ცხ.ძ.', 'slug' => 'bmw', 'id' => '210'],
            ['brand' => 'Mercedes-Benz', 'model' => 'C-Class', 'years' => '2021–2024', 'body' => 'სედანი', 'engine' => '1.5 ბენზინი', 'power' => '204 ცხ.ძ.', 'slug' => 'mercedes-benz', 'id' => '300'],
            ['brand' => 'Hyundai', 'model' => 'Tucson', 'years' => '2020–2024', 'body' => 'ჯიპი', 'engine' => '2.0 ბენზინი', 'power' => '156 ცხ.ძ.', 'slug' => 'hyundai', 'id' => '400'],
        ];

        return $base;
    }

    private function legacyId(): int
    {
        $id = auth()->user()?->legacy_platform_user_id;
        abort_unless(is_numeric($id), 403);

        return (int) $id;
    }
}
