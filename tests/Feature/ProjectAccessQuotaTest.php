<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Account\Projects;
use App\Models\User;
use Carapi\Platform\ApiKeys;
use Carapi\Platform\Connection as PlatformConnection;
use Carapi\Platform\MemoryUsageStore;
use Carapi\Platform\ProjectRepository;
use Carapi\Platform\SqliteUsageStore;
use Carapi\Platform\UsageLimiter;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PDO;
use Tests\TestCase;

final class ProjectAccessQuotaTest extends TestCase
{
    use SeedsPublicCatalog {
        setUp as seedCatalog;
        tearDown as forgetCatalog;
    }

    private PDO $platform;

    protected function setUp(): void
    {
        $this->seedCatalog();
        $this->platform = DB::connection()->getPdo();
        $this->platform->sqliteCreateFunction('now', static fn (): string => gmdate('Y-m-d H:i:s'));
        DB::statement('DROP TABLE IF EXISTS platform.projects');
        foreach ([
            'CREATE TABLE platform.users (id integer primary key, status text not null, email_verified_at text)',
            'CREATE TABLE platform.projects (id integer primary key, user_id integer not null, name text, website_url text, status text not null default \'active\', created_at text, updated_at text)',
            'CREATE TABLE platform.api_keys (id integer primary key, project_id integer not null, name text, key_selector text not null, secret_hash text not null, scope text not null default \'catalog:read\', created_at text, expires_at text, revoked_at text, last_used_at text)',
            'CREATE TABLE platform.project_limits (project_id integer primary key, per_minute integer not null, monthly_quota integer not null, per_day integer not null)',
            'CREATE TABLE platform.widgets (project_id integer primary key, enabled integer not null default 1, public_key text not null, allowed_origins text not null default \'[]\', updated_at text)',
            'CREATE TABLE platform.monthly_usage (project_id integer not null, period text not null, used_count integer not null, primary key (project_id, period))',
            'CREATE TABLE platform.daily_usage (project_id integer not null, day text not null, route text not null, used_count integer not null, primary key (project_id, day, route))',
        ] as $sql) {
            DB::statement($sql);
        }
        DB::table('platform.users')->insert([
            ['id' => 3, 'status' => 'active', 'email_verified_at' => '2026-01-01 00:00:00'],
            ['id' => 4, 'status' => 'active', 'email_verified_at' => '2026-01-01 00:00:00'],
        ]);
        DB::table('platform.projects')->insert([
            ['id' => 7, 'user_id' => 3, 'name' => 'Catalog', 'website_url' => 'https://owner.example', 'status' => 'active'],
            ['id' => 8, 'user_id' => 4, 'name' => 'Other project', 'website_url' => null, 'status' => 'active'],
        ]);
        $productId = (int) DB::table('api_products')->where('slug', 'cars')->value('id');
        $planId = (int) DB::table('api_plans')->where('code', 'cars-included')->value('id');
        DB::table('api_entitlements')->insert([
            'legacy_user_id' => 4,
            'api_product_id' => $productId,
            'api_plan_id' => $planId,
            'status' => 'active',
            'starts_at' => now(),
            'source' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        PlatformConnection::use($this->platform);
    }

    protected function tearDown(): void
    {
        PlatformConnection::reset();
        foreach ([
            'CARAPI_USAGE_NOW',
            'CARAPI_USAGE_SQLITE',
            'CARAPI_WIDGET_ENABLED',
            'CARAPI_PLATFORM_ENABLED',
        ] as $name) {
            $this->env($name, null);
        }
        $this->forgetCatalog();
    }

    public function test_guest_revoked_and_foreign_project_are_rejected_without_quota_headers(): void
    {
        $this->useIssuedKeys();
        $issued = $this->issueKey(3, 7);

        $guest = $this->getJson('/api/v1/brands');
        $guest->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthorized')
            ->assertHeader('WWW-Authenticate', 'Bearer');
        $guest->assertHeaderMissing('X-Quota-Limit');
        $guest->assertHeaderMissing('X-RateLimit-Limit');
        $this->assertSame(0, MemoryUsageStore::shared()->previewMonth(7, UsageLimiter::now())['used']);

        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/brands?limit=1')
            ->assertOk()
            ->assertHeader('X-Quota-Limit', '10000')
            ->assertHeader('X-RateLimit-Limit', '60');

        $repo = new ProjectRepository($this->platform);
        $keyId = (int) $repo->keys(3, 7)[0]['id'];
        $this->assertTrue($repo->revoke(3, 7, $keyId));
        $revoked = $this->withHeader('Authorization', 'Bearer '.$issued['token'])->getJson('/api/v1/brands');
        $revoked->assertUnauthorized()->assertJsonPath('error.code', 'unauthorized');
        $this->assertStringNotContainsString($issued['secret'], (string) $revoked->getContent());
        $this->assertSame(1, MemoryUsageStore::shared()->previewMonth(7, UsageLimiter::now())['used']);

        DB::table('platform.projects')->where('id', 7)->update(['status' => 'active']);
        $replacement = $this->issueKey(3, 7, 'replacement');
        $this->withHeader('Authorization', 'Bearer '.$replacement['token'])
            ->getJson('/api/v1/brands?limit=1')
            ->assertOk()
            ->assertHeader('X-Quota-Remaining', '9998');
        $this->assertSame(2, MemoryUsageStore::shared()->previewMonth(7, UsageLimiter::now())['used']);
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/brands')
            ->assertUnauthorized();
    }

    public function test_disabled_project_suspended_owner_and_unverified_email_reject_a_real_key(): void
    {
        $this->useIssuedKeys();
        $issued = $this->issueKey(3, 7);

        DB::table('platform.projects')->where('id', 7)->update(['status' => 'disabled']);
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/brands')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');

        DB::table('platform.projects')->where('id', 7)->update(['status' => 'active']);
        DB::table('platform.users')->where('id', 3)->update(['status' => 'suspended']);
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/brands')
            ->assertForbidden();

        DB::table('platform.users')->where('id', 3)->update(['status' => 'active', 'email_verified_at' => null]);
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/brands')
            ->assertForbidden();

        DB::table('platform.users')->where('id', 3)->update(['email_verified_at' => '2026-01-01 00:00:00']);
        DB::table('platform.api_keys')->where('key_selector', $issued['selector'])->update(['expires_at' => '2000-01-01 00:00:00']);
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/brands')
            ->assertUnauthorized();

        $this->assertSame(0, MemoryUsageStore::shared()->previewMonth(7, UsageLimiter::now())['used']);
        $this->assertStringNotContainsString($issued['hash'], (string) DB::table('platform.api_keys')->where('key_selector', $issued['selector'])->value('name'));
    }

    public function test_sanctum_project_header_cannot_cross_owners_and_a_session_is_not_a_key(): void
    {
        $this->useIssuedKeys();
        $owner = $this->user(3);
        $other = $this->user(4);
        $token = $owner->createToken('cars', ['cars:read'])->plainTextToken;
        $star = $owner->createToken('all', ['*'])->plainTextToken;
        $issued = $this->issueKey(3, 7);

        $this->actingAs($other)->getJson('/api/v1/brands')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthorized');
        $this->actingAs($other)
            ->withHeader('X-CARAPI-PROJECT', '7')
            ->getJson('/api/v1/brands')
            ->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-CARAPI-PROJECT', '8')
            ->getJson('/api/v1/brands')
            ->assertForbidden()
            ->assertDontSee('Other project');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-CARAPI-PROJECT', '7')
            ->getJson('/api/v1/brands?limit=1')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$star)
            ->withHeader('X-CARAPI-PROJECT', '7')
            ->getJson('/api/v1/coverage')
            ->assertOk();

        $empty = $owner->createToken('none', [])->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$empty)
            ->withHeader('X-CARAPI-PROJECT', '7')
            ->getJson('/api/v1/brands')
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->withHeader('X-CARAPI-PROJECT', '8')
            ->getJson('/api/v1/brands?limit=1')
            ->assertOk();
        $this->assertSame(3, MemoryUsageStore::shared()->previewMonth(7, UsageLimiter::now())['used']);
        $this->assertSame(0, MemoryUsageStore::shared()->previewMonth(8, UsageLimiter::now())['used']);

        $this->withHeader('Authorization', 'Bearer geoapi_pk_'.str_repeat('ab', 16))
            ->getJson('/api/v1/brands')
            ->assertUnauthorized();
    }

    public function test_sanctum_token_follows_the_same_owner_and_expiry_rules_as_a_key(): void
    {
        $this->useIssuedKeys();
        $owner = $this->user(3);
        $call = fn (string $token) => $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-CARAPI-PROJECT', '7')
            ->getJson('/api/v1/brands?limit=1');

        $expired = $owner->createToken('old', ['cars:read'], now()->subMinute())->plainTextToken;
        $call($expired)->assertUnauthorized();

        $token = $owner->createToken('cars', ['cars:read'], now()->addDay())->plainTextToken;
        $call($token)->assertOk();

        DB::table('platform.users')->where('id', 3)->update(['status' => 'suspended']);
        $call($token)->assertForbidden();

        DB::table('platform.users')->where('id', 3)->update(['status' => 'active', 'email_verified_at' => null]);
        $call($token)->assertForbidden();

        DB::table('platform.users')->where('id', 3)->update(['email_verified_at' => '2026-01-01 00:00:00']);
        config(['sanctum.expiration' => 60]);
        $this->travel(2)->hours();
        $call($token)->assertUnauthorized();
    }

    public function test_cabinet_hides_another_project_and_shows_a_secret_only_on_the_create_response(): void
    {
        $owner = $this->user(3);
        $other = $this->user(4);
        $unverified = $this->user(5, false);
        $repo = new ProjectRepository($this->platform);

        Livewire::actingAs($owner)->test(Projects::class)
            ->set('name', 'Alpha')
            ->set('website_url', '')
            ->call('create')
            ->assertDontSee('Other project');

        $alphaId = (int) DB::table('platform.projects')->where('name', 'Alpha')->value('id');
        $this->assertGreaterThan(0, $alphaId);
        $limits = DB::table('platform.project_limits')->where('project_id', $alphaId)->first();
        $this->assertSame(60, (int) $limits->per_minute);
        $this->assertSame(10000, (int) $limits->monthly_quota);
        $this->assertSame(2000, (int) $limits->per_day);

        DB::table('platform.monthly_usage')->insert([
            'project_id' => $alphaId,
            'period' => gmdate('Y-m-01'),
            'used_count' => 4,
        ]);

        $created = Livewire::actingAs($owner)->test(Projects::class)
            ->set('projectId', $alphaId)
            ->set('key_name', 'Server')
            ->set('key_kind', 'secret')
            ->call('createKey');
        $this->assertSame(1, preg_match('/geoapi_sk_[0-9a-f]{32}_[A-Za-z0-9_-]{43}/', $created->html(), $match), $created->html());
        $token = $match[0];
        $parsed = ApiKeys::parse($token);
        $this->assertIsArray($parsed);
        $this->assertSame(4, (int) DB::table('platform.monthly_usage')->where('project_id', $alphaId)->value('used_count'));
        $row = DB::table('platform.api_keys')->where('key_selector', $parsed['selector'])->first();
        $this->assertSame($alphaId, (int) $row->project_id);
        $this->assertSame(hash('sha256', $parsed['secret']), (string) $row->secret_hash);
        $this->assertStringNotContainsString($parsed['secret'], (string) $row->name);

        $later = $this->actingAs($owner)->get('/dashboard');
        $later->assertOk();
        $later->assertDontSee($parsed['secret'], false);
        $later->assertDontSee((string) $row->secret_hash, false);
        $later->assertDontSee($token, false);

        Livewire::actingAs($other)->test(Projects::class)
            ->assertDontSee('Alpha')
            ->assertDontSee('Catalog')
            ->call('selectProject', $alphaId)
            ->assertSet('projectId', 8);

        $keyId = (int) $row->id;
        Livewire::actingAs($other)->test(Projects::class)
            ->set('projectId', $alphaId)
            ->call('revokeKey', $keyId);
        $this->assertNull(DB::table('platform.api_keys')->where('id', $keyId)->value('revoked_at'));

        Livewire::actingAs($other)->test(Projects::class)
            ->set('projectId', 7)
            ->set('key_name', 'Stolen')
            ->set('key_kind', 'secret')
            ->call('createKey');
        $this->assertSame(0, DB::table('platform.api_keys')->where('project_id', 7)->count());

        $unverified = Livewire::actingAs($unverified)->test(Projects::class)
            ->set('name', 'Beta')
            ->call('create')
            ->set('key_name', 'Secret')
            ->set('key_kind', 'secret')
            ->call('createKey');
        $unverified->assertHasErrors('key_name');
        $this->assertNull(session('revealed_key'));
        $betaId = (int) DB::table('platform.projects')->where('user_id', 5)->where('name', 'Beta')->value('id');
        $this->assertGreaterThan(0, $betaId);
        $this->assertNull($repo->findOwned(3, $betaId));

        $this->assertTrue($repo->revoke(3, $alphaId, $keyId));
        $this->assertNotNull(DB::table('platform.api_keys')->where('id', $keyId)->value('revoked_at'));
        $repo->setStatus(3, $alphaId, 'disabled');
        $repo->setStatus(3, $alphaId, 'active');
        $this->assertNotNull(DB::table('platform.api_keys')->where('id', $keyId)->value('revoked_at'));
    }

    public function test_utc_windows_headers_and_shared_widget_counter(): void
    {
        $now = gmmktime(23, 59, 30, 1, 31, 2026);
        $this->env('CARAPI_USAGE_NOW', (string) $now);
        MemoryUsageStore::shared()->perMinute = 60;

        $ok = $this->getJson('/api/v1/brands?limit=1')->assertOk();
        $ok->assertHeader('X-Quota-Limit', '10000');
        $ok->assertHeader('X-Quota-Remaining', '9999');
        $ok->assertHeader('X-Quota-Reset', (string) gmmktime(0, 0, 0, 2, 1, 2026));
        $ok->assertHeader('X-RateLimit-Limit', '60');
        $ok->assertHeader('X-RateLimit-Remaining', '59');
        $ok->assertHeader('X-RateLimit-Reset', (string) ($now - 30 + 60));

        MemoryUsageStore::reset();
        $this->env('CARAPI_USAGE_NOW', (string) gmmktime(12, 0, 30, 6, 15, 2026));
        MemoryUsageStore::shared()->perMinute = 1;
        $this->getJson('/api/v1/brands?limit=1')->assertOk();
        $this->getJson('/api/v1/brands?limit=1')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED')
            ->assertHeader('Retry-After', '30')
            ->assertHeader('X-RateLimit-Limit', '1')
            ->assertHeader('X-RateLimit-Remaining', '0')
            ->assertHeaderMissing('X-Quota-Limit');

        MemoryUsageStore::reset();
        $this->env('CARAPI_USAGE_NOW', (string) $now);
        MemoryUsageStore::shared()->perMinute = 100;
        MemoryUsageStore::shared()->perDay = 1;
        $this->getJson('/api/v1/brands?limit=1')->assertOk();
        $this->getJson('/api/v1/brands?limit=1')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'DAILY_QUOTA_EXCEEDED')
            ->assertHeader('Retry-After');
        $this->env('CARAPI_USAGE_NOW', (string) gmmktime(0, 0, 30, 2, 1, 2026));
        $this->getJson('/api/v1/brands?limit=1')->assertOk()->assertHeader('X-Quota-Remaining', '9999');

        MemoryUsageStore::reset();
        $this->env('CARAPI_USAGE_NOW', (string) $now);
        MemoryUsageStore::shared()->perDay = 2000;
        MemoryUsageStore::shared()->monthlyQuota = 1;
        $this->getJson('/api/v1/brands?limit=1')->assertOk();
        $this->getJson('/api/v1/coverage')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'MONTHLY_QUOTA_EXCEEDED');
        $this->env('CARAPI_USAGE_NOW', (string) gmmktime(0, 0, 30, 2, 1, 2026));
        $this->getJson('/api/v1/coverage')->assertOk();
        $stats = MemoryUsageStore::shared()->stats(7, gmmktime(0, 0, 30, 2, 1, 2026));
        $this->assertSame(1, $stats['used']);
        $this->assertEqualsCanonicalizing(['brands', 'coverage'], array_column($stats['routes'], 'route'));

        MemoryUsageStore::reset();
        MemoryUsageStore::shared()->perMinute = 100;
        $this->getJson('/api/v1/brands?limit=1001')->assertStatus(422);
        $this->assertSame(0, MemoryUsageStore::shared()->previewMonth(7, UsageLimiter::now())['used']);

        MemoryUsageStore::reset();
        MemoryUsageStore::shared()->perMinute = null;
        $this->getJson('/api/v1/brands')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'unavailable')
            ->assertJsonMissingPath('data');

        MemoryUsageStore::reset();
        MemoryUsageStore::shared()->failCommit = true;
        $failed = $this->getJson('/api/v1/brands?limit=1');
        $failed->assertStatus(503)->assertJsonPath('error.code', 'unavailable');
        $this->assertStringNotContainsString('audi', (string) $failed->getContent());
        $this->assertSame(0, MemoryUsageStore::shared()->previewMonth(7, UsageLimiter::now())['used']);
    }

    public function test_widget_origin_is_rejected_when_missing_and_shares_the_project_quota(): void
    {
        $this->env('CARAPI_WIDGET_ENABLED', '1');
        $this->env('CARAPI_PLATFORM_ENABLED', '1');
        $key = 'geoapi_pk_'.str_repeat('ab', 16);
        DB::table('platform.widgets')->insert([
            'project_id' => 7,
            'enabled' => 1,
            'public_key' => $key,
            'allowed_origins' => json_encode(['https://app.example']),
        ]);
        $path = '/widget/v1/'.$key.'/brands';

        $denied = $this->postJson($path, []);
        $denied->assertForbidden()->assertJsonPath('error.code', 'forbidden');
        $this->assertSame(0, MemoryUsageStore::shared()->previewMonth(7, UsageLimiter::now())['used']);

        $spoofed = $this->withHeader('Origin', 'https://app.example')->postJson($path, []);
        $spoofed->assertOk();
        $this->assertSame(1, MemoryUsageStore::shared()->previewMonth(7, UsageLimiter::now())['used']);

        $this->getJson('/api/v1/brands?limit=1')->assertOk()->assertHeader('X-Quota-Remaining', '9998');
        $routes = array_column(MemoryUsageStore::shared()->stats(7, UsageLimiter::now())['routes'], 'route');
        $this->assertEqualsCanonicalizing(['widget:brands', 'brands'], $routes);

        DB::table('platform.widgets')->where('project_id', 7)->update(['enabled' => 0]);
        $this->withHeader('Origin', 'https://app.example')->postJson($path, [])
            ->assertForbidden();
    }

    public function test_last_used_updates_at_most_once_per_minute(): void
    {
        $this->useIssuedKeys();
        $now = gmmktime(12, 0, 10, 6, 15, 2026);
        $this->env('CARAPI_USAGE_NOW', (string) $now);
        $issued = $this->issueKey(3, 7);

        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/brands?limit=1')
            ->assertOk();
        $first = (string) DB::table('platform.api_keys')->where('key_selector', $issued['selector'])->value('last_used_at');
        $this->assertSame(gmdate('Y-m-d H:i:s', $now), $first);

        $this->env('CARAPI_USAGE_NOW', (string) ($now + 30));
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/brands/missing/models')
            ->assertNotFound();
        $this->assertSame($first, (string) DB::table('platform.api_keys')->where('key_selector', $issued['selector'])->value('last_used_at'));
        $this->assertSame(1, MemoryUsageStore::shared()->previewMonth(7, $now + 30)['used']);

        $this->env('CARAPI_USAGE_NOW', (string) ($now + 61));
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/brands?limit=1')
            ->assertOk();
        $this->assertSame(
            gmdate('Y-m-d H:i:s', $now + 61),
            (string) DB::table('platform.api_keys')->where('key_selector', $issued['selector'])->value('last_used_at')
        );
    }

    public function test_six_parallel_requests_with_one_unit_leave_a_single_success(): void
    {
        $path = sys_get_temp_dir().'/geoapi-quota-'.bin2hex(random_bytes(4)).'.sqlite';
        $store = new SqliteUsageStore($path);
        $store->allow(7, 60, 2000, 1);
        $procs = [];
        try {
            for ($i = 0; $i < 6; $i++) {
                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, base_path('tests/bin/quota-hit.php'), $path],
                    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                    base_path()
                );
                $this->assertIsResource($process);
                $procs[] = ['process' => $process, 'pipes' => $pipes];
            }
            $statuses = [];
            $codes = [];
            foreach ($procs as $proc) {
                $output = stream_get_contents($proc['pipes'][1]);
                $error = stream_get_contents($proc['pipes'][2]);
                fclose($proc['pipes'][1]);
                fclose($proc['pipes'][2]);
                $exit = proc_close($proc['process']);
                $this->assertSame(0, $exit, $error."\n".$output);
                $decoded = json_decode((string) $output, true);
                $this->assertIsArray($decoded, (string) $output);
                $statuses[] = (int) $decoded['status'];
                $codes[] = $decoded['code'];
            }
            $this->assertSame(
                [1, 5],
                [
                    count(array_filter($statuses, static fn (int $status): bool => $status === 200)),
                    count(array_filter($statuses, static fn (int $status): bool => $status === 429)),
                ],
                json_encode(['statuses' => $statuses, 'codes' => $codes])
            );
            $denied = array_values(array_filter($codes, static fn (mixed $code): bool => $code !== null));
            $this->assertSame(['MONTHLY_QUOTA_EXCEEDED'], array_values(array_unique($denied)));
            $this->assertCount(5, $denied);
            $this->assertSame(1, (new SqliteUsageStore($path))->monthUsed(7, time()));
        } finally {
            foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function test_a_broken_meter_returns_503_instead_of_an_unmetered_200(): void
    {
        $this->env('CARAPI_USAGE_SQLITE', sys_get_temp_dir().'/geoapi-missing-'.bin2hex(random_bytes(3)).'/quota.sqlite');
        $this->env('CARAPI_USAGE_MEMORY', '0');
        $response = $this->getJson('/api/v1/brands?limit=1');
        $response->assertStatus(503)->assertJsonPath('error.code', 'unavailable');
        $this->assertStringNotContainsString('toyota', (string) $response->getContent());
    }

    private function useIssuedKeys(): void
    {
        $this->env('CARAPI_CATALOG_TEST_PROJECT', null);
    }

    /** @return array{selector:string,secret:string,token:string,hash:string} */
    private function issueKey(int $userId, int $projectId, string $name = 'server'): array
    {
        $issued = ApiKeys::issue();
        $this->assertTrue((new ProjectRepository($this->platform))->insertKey(
            $userId,
            $projectId,
            $name,
            $issued['selector'],
            $issued['hash'],
            null
        ));
        $this->assertSame(1, preg_match('/^geoapi_sk_[0-9a-f]{32}_[A-Za-z0-9_-]{43}$/', $issued['token']));
        $this->assertTrue(ApiKeys::matches($issued['secret'], $issued['hash']));

        return $issued;
    }

    private function user(int $legacyId, bool $verified = true): User
    {
        $user = User::factory()->create([
            'email_verified_at' => $verified ? now() : null,
        ]);
        $user->forceFill(['legacy_platform_user_id' => $legacyId])->save();

        return $user;
    }

    private function env(string $name, ?string $value): void
    {
        if ($value === null) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);

            return;
        }
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv($name.'='.$value);
    }
}
