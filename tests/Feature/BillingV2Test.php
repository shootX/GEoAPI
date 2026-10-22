<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Billing\Entitlements;
use App\Billing\NbgRate;
use App\Billing\V2\Backfill;
use App\Billing\V2\Catalog;
use App\Billing\V2\Checkout;
use App\Billing\V2\Flag;
use App\Billing\V2\Gate;
use App\Billing\V2\Ledger;
use App\Billing\V2\Lifecycle;
use App\Billing\V2\ProjectLimitReached;
use App\Billing\V2\Workspace;
use App\Catalog\PublicSpec;
use App\Livewire\Account\Billing;
use App\Livewire\Account\Projects;
use App\Models\User;
use Carapi\Platform\ApiKeys;
use Carapi\Platform\Connection as PlatformConnection;
use Carapi\Platform\MemoryUsageStore;
use Carapi\Platform\ProjectRepository;
use Carapi\Platform\UsageLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Livewire\Livewire;
use PDO;
use Tests\TestCase;

final class BillingV2Test extends TestCase
{
    use SeedsPublicCatalog {
        setUp as seedCatalog;
        tearDown as forgetCatalog;
    }

    private PDO $platform;

    private string $privateKey;

    protected function setUp(): void
    {
        $this->seedCatalog();
        $this->platform = DB::connection()->getPdo();
        $this->platform->sqliteCreateFunction('now', static fn (): string => gmdate('Y-m-d H:i:s'));
        $this->platformSchema();
        PlatformConnection::use($this->platform);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $private);
        $this->privateKey = $private;
        $details = openssl_pkey_get_details($key);
        config(['services.bog.public_key' => $details['key']]);
    }

    protected function tearDown(): void
    {
        PlatformConnection::reset();
        config(['billing.v2' => false, 'billing.v2_business_rpm' => false]);
        foreach (['CARAPI_WIDGET_ENABLED', 'CARAPI_PLATFORM_ENABLED', 'CARAPI_USAGE_NOW'] as $name) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
        }
        $this->forgetCatalog();
    }

    public function test_flag_off_keeps_the_public_prices_and_guide(): void
    {
        $this->get('/pricing')
            ->assertOk()
            ->assertSee('Basic')
            ->assertSee('$59')
            ->assertSee('$99')
            ->assertDontSee('data-billing-v2="1"', false);

        $guide = PublicSpec::narrative('en');
        $limits = $this->section($guide, 'guide-limits');
        $this->assertStringContainsString('59 USD', $limits[0]);
        $this->assertStringNotContainsString('PROJECT_CAP_EXCEEDED', $limits[3]);
        $this->assertSame(19380, Catalog::usdCents('starter', 'year'));
        $sql = (string) file_get_contents(base_path('database/migrations/2026_12_31_930000_billing_v2.php'));
        $this->assertStringNotContainsString('ALTER TABLE', $sql);
        $this->assertStringContainsString('boolean(', $sql);
        $this->assertStringNotContainsString('platform.project_limits', $sql);
    }

    public function test_public_copy_uses_v2_numbers_when_the_flag_is_on(): void
    {
        config(['billing.v2' => true]);
        $this->get('/')->assertOk()->assertSee('1 000 წარმატებული')->assertDontSee('$0 · 100 ');
        $this->get('/terms')->assertOk()->assertSee('Starter — $19/თვე')->assertDontSee('10 000 წარმატებული მოთხოვნა. ეს კომერციული');
        $this->withSession(['locale' => 'en'])->get('/')->assertOk()->assertSee('1 000 successful')->assertDontSee('X-Quota-Limit: 2000');
        $this->withSession(['locale' => 'en'])->get('/terms')->assertOk()->assertSee('Starter is $19/month')->assertDontSee('Unlimited');
    }

    public function test_public_pricing_lists_the_new_tiers_when_the_flag_is_on(): void
    {
        config(['billing.v2' => true]);

        $this->withSession(['locale' => 'en'])->get('/pricing')
            ->assertOk()
            ->assertSee('data-billing-v2="1"', false)
            ->assertSee('Starter')
            ->assertSee('$19')
            ->assertSee('$193.80')
            ->assertSee('−15%')
            ->assertSee('25 000')
            ->assertSee('150 000')
            ->assertSee('There is no automatic charge.')
            ->assertDontSee('Premium')
            ->assertDontSee('Business')
            ->assertDontSee('240 requests / minute');

        $limits = $this->section(PublicSpec::narrative('en'), 'guide-limits');
        $this->assertStringContainsString('19 USD', $limits[0]);
        $this->assertStringContainsString('PROJECT_CAP_EXCEEDED', $limits[3]);
        $this->assertStringNotContainsString('240', $limits[0]);
    }

    public function test_three_projects_share_one_quota_and_a_cap_stops_one_project(): void
    {
        config(['billing.v2' => true]);
        $user = $this->owner();
        $this->projects();
        $workspaceId = $this->shared($user, 'starter');
        $now = time();
        $limiter = UsageLimiter::fromEnv();

        foreach ([7, 11, 12] as $projectId) {
            $saved = $limiter->commitSuccess($projectId, 'brands', $now);
            $this->assertTrue($saved['ok']);
        }

        $this->assertSame(3, $this->used($workspaceId));
        $this->assertSame(1, $this->projectUsed($workspaceId, 7));
        $this->assertSame(1, $this->projectUsed($workspaceId, 11));
        $this->assertSame(1, $this->projectUsed($workspaceId, 12));
        $this->assertSame(0, DB::table('platform.monthly_usage')->count());

        $this->assertTrue(Gate::setCap(3, 7, 1));
        $blocked = $limiter->commitSuccess(7, 'brands', $now);
        $this->assertFalse($blocked['ok']);
        $this->assertSame('cap', $blocked['reason']);
        $this->assertSame(3, $this->used($workspaceId));
        $other = $limiter->commitSuccess(11, 'brands', $now);
        $this->assertTrue($other['ok']);
        $this->assertSame(4, $this->used($workspaceId));
        $this->assertFalse(Gate::setCap(3, 8, 1));

        $this->getJson('/api/v1/brands?limit=1')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'PROJECT_CAP_EXCEEDED');
    }

    public function test_workspace_rpm_is_shared_and_the_ip_limit_stays_separate(): void
    {
        config(['billing.v2' => true]);
        $user = $this->owner();
        $this->projects();
        $workspaceId = $this->shared($user, 'free');
        DB::table('billing_workspace_subscriptions')->where('workspace_id', $workspaceId)->update(['rpm' => 2]);
        $limiter = UsageLimiter::fromEnv();
        $now = time();

        $this->assertFalse($limiter->hitProject(7, $now)['exceeded']);
        $this->assertFalse($limiter->hitProject(11, $now)['exceeded']);
        $third = $limiter->hitProject(12, $now);
        $this->assertTrue($third['exceeded']);
        $this->assertSame(2, $third['limit']);
        $this->assertFalse($limiter->hitIp('203.0.113.9', $now)['exceeded']);
    }

    public function test_project_slot_and_extra_project_purchase(): void
    {
        config(['billing.v2' => true]);
        $user = $this->owner();
        DB::table('platform.users')->where('id', 3)->update(['grandfathered' => 0]);
        DB::table('platform.projects')->where('user_id', 3)->delete();
        $repo = new ProjectRepository($this->platform);
        $first = $repo->insert(3, 'One', 'https://one.example');
        $workspace = Workspace::find(3);
        $this->assertNotNull($workspace);
        $this->assertTrue(Flag::is($workspace ? DB::table('billing_workspace_subscriptions')->where('workspace_id', $workspace->id)->value('shared_quota') : false));
        Ledger::consume((int) $workspace->id, $first, time());
        $used = $this->used((int) $workspace->id);

        try {
            $repo->insert(3, 'Two', 'https://two.example');
            $this->fail('A second project must be refused.');
        } catch (ProjectLimitReached) {
        }

        $this->assertSame($used, $this->used((int) $workspace->id));
        $this->pay($user, 'extra-project', 'month', $first);
        $third = $repo->insert(3, 'Two', 'https://two.example');
        $this->assertGreaterThan(0, $third);
        $this->assertSame($used, $this->used((int) $workspace->id));
        $this->assertSame(2, Workspace::allowedProjects(
            Workspace::subscription((int) $workspace->id),
            (int) $workspace->id,
            Workspace::stamp(),
        ));
    }

    public function test_an_upgrade_late_in_the_period_buys_a_full_month(): void
    {
        config(['billing.v2' => true]);
        $user = $this->owner();
        $this->projects();
        $start = Workspace::stamp(time() - 25 * 86400);
        $workspaceId = Workspace::create((int) $user->id, 3, 'signup', [
            'plan_code' => 'free', 'interval' => 'month', 'monthly_quota' => 1000, 'rpm' => Catalog::rpm('free'),
            'included_projects' => 1, 'period_start' => $start, 'period_end' => Workspace::plusMonth($start),
            'term_end' => Workspace::plusMonth($start), 'shared_quota' => true, 'source' => 'test',
        ]);
        $monthFromNow = strtotime(Workspace::plusMonth(Workspace::stamp()).' UTC');

        $this->pay($user, 'starter', 'month');
        $starter = Workspace::subscription($workspaceId);
        $this->assertSame('starter', $starter->plan_code);
        $this->assertSame($start, (string) $starter->period_start, 'usage of the current period is kept');
        $this->assertGreaterThanOrEqual($monthFromNow - 5, strtotime($starter->term_end.' UTC'));

        DB::table('billing_workspace_subscriptions')->where('workspace_id', $workspaceId)->update(['term_end' => Workspace::stamp(time() + 5 * 86400)]);
        $this->pay($user, 'growth', 'month');
        $growth = Workspace::subscription($workspaceId);
        $this->assertSame('growth', $growth->plan_code);
        $this->assertGreaterThanOrEqual($monthFromNow - 5, strtotime($growth->term_end.' UTC'));

        Lifecycle::rollLocked($workspaceId, time() + 10 * 86400);
        $this->assertSame('growth', Workspace::subscription($workspaceId)->plan_code, 'the paid plan outlives the old 5-day term');
    }

    public function test_an_extra_project_lasts_a_month_from_payment(): void
    {
        config(['billing.v2' => true]);
        $user = $this->owner();
        $this->projects();
        $workspaceId = $this->shared($user, 'free');
        DB::table('billing_workspace_subscriptions')->where('workspace_id', $workspaceId)->update(['period_end' => Workspace::stamp(time() + 5 * 86400)]);

        $this->pay($user, 'extra-project', 'month');

        $endsAt = (string) DB::table('billing_project_addons')->where('workspace_id', $workspaceId)->value('ends_at');
        $this->assertSame(Workspace::plusMonth(Workspace::stamp()), $endsAt);
    }

    public function test_adding_a_month_or_year_never_skips_past_the_month_end(): void
    {
        $this->assertSame('2027-02-28 10:00:00', Workspace::plusMonth('2027-01-31 10:00:00'));
        $this->assertSame('2028-02-29 10:00:00', Workspace::plusMonth('2028-01-31 10:00:00'));
        $this->assertSame('2027-04-30 10:00:00', Workspace::plusMonth('2027-03-31 10:00:00'));
        $this->assertSame('2029-02-28 10:00:00', Workspace::plusYear('2028-02-29 10:00:00'));
    }

    public function test_upgrade_keeps_usage_downgrade_waits_and_the_month_resets(): void
    {
        config(['billing.v2' => true]);
        $user = $this->owner();
        $this->projects();
        $workspaceId = $this->shared($user, 'free');
        $before = (string) DB::table('billing_workspace_subscriptions')->where('workspace_id', $workspaceId)->value('period_start');
        Ledger::consume($workspaceId, 7, time());
        Ledger::consume($workspaceId, 11, time());
        $this->pay($user, 'starter', 'month');
        $sub = Workspace::subscription($workspaceId);
        $this->assertSame('starter', $sub->plan_code);
        $this->assertSame($before, (string) $sub->period_start);
        $this->assertSame(2, $this->used($workspaceId));
        $this->assertSame(25000, (int) $sub->monthly_quota);

        $term = (string) $sub->term_end;
        $this->pay($user, 'starter', 'month');
        $extended = (string) Workspace::subscription($workspaceId)->term_end;
        $this->assertGreaterThan($term, $extended);
        $this->assertSame(2, $this->used($workspaceId));

        $this->assertTrue(Lifecycle::schedule(3, 'free'));
        $this->assertSame('free', Workspace::subscription($workspaceId)->pending_plan_code);
        $this->assertSame('starter', Workspace::subscription($workspaceId)->plan_code);

        $stamp = Workspace::stamp(time() - 120);
        DB::table('billing_workspace_subscriptions')->where('workspace_id', $workspaceId)->update([
            'period_end' => $stamp,
            'term_end' => Workspace::plusMonth(Workspace::stamp()),
            'pending_plan_code' => null,
        ]);
        Lifecycle::rollLocked($workspaceId, time());
        $rolled = Workspace::subscription($workspaceId);
        $this->assertSame('starter', $rolled->plan_code);
        $this->assertSame(0, (int) (DB::table('billing_period_usage')->where('workspace_id', $workspaceId)->where('period_start', $rolled->period_start)->value('used_count') ?? 0));
        $this->assertSame(2, (int) DB::table('billing_period_usage')->where('workspace_id', $workspaceId)->where('period_start', $before)->value('used_count'));

        DB::table('billing_workspace_subscriptions')->where('workspace_id', $workspaceId)->update([
            'interval' => 'year',
            'pending_plan_code' => 'free',
            'pending_interval' => 'month',
            'period_end' => Workspace::stamp(time() - 60),
            'term_end' => Workspace::stamp(time() - 30),
        ]);
        Lifecycle::rollLocked($workspaceId, time());
        $ended = Workspace::subscription($workspaceId);
        $this->assertSame('free', $ended->plan_code);
        $this->assertSame(1000, (int) $ended->monthly_quota);
        $this->assertNull($ended->pending_plan_code);
    }

    public function test_credits_are_spent_after_quota_and_expire(): void
    {
        config(['billing.v2' => true]);
        $user = $this->owner();
        $this->projects();
        $workspaceId = $this->shared($user, 'starter');
        DB::table('billing_workspace_subscriptions')->where('workspace_id', $workspaceId)->update(['monthly_quota' => 1]);
        $this->pay($user, 'credits:starter', 'month');
        $this->assertSame(10000, Workspace::creditRemaining($workspaceId, Workspace::stamp()));
        $row = DB::table('billing_credit_ledger')->where('workspace_id', $workspaceId)->first();
        $this->assertNotNull($row);
        $expires = strtotime((string) $row->expires_at.' UTC');
        $this->assertEqualsWithDelta(time() + (90 * 86400), $expires, 120);

        $now = time();
        $this->assertTrue(Ledger::consume($workspaceId, 7, $now)['ok']);
        $this->assertSame(10000, Workspace::creditRemaining($workspaceId, Workspace::stamp($now)));
        $this->assertTrue(Ledger::consume($workspaceId, 11, $now)['ok']);
        $this->assertSame(9999, Workspace::creditRemaining($workspaceId, Workspace::stamp($now)));
        $this->assertSame(1, $this->used($workspaceId));

        DB::table('billing_credit_ledger')->where('workspace_id', $workspaceId)->update([
            'expires_at' => Workspace::stamp($now - 60),
        ]);
        $this->assertFalse(Ledger::consume($workspaceId, 12, $now)['ok']);
        $this->assertSame(0, DB::table('billing_orders')->where('plan_code', 'like', 'v2:overage%')->count());

        try {
            Checkout::open((int) $user->id, 3, 7, 'credits:growth', 'month', $this->rate(), $this->buyer());
            $this->fail('A Growth pack cannot be bought on Starter.');
        } catch (InvalidArgumentException) {
        }
    }

    public function test_bog_payment_replay_and_partial_and_full_refunds(): void
    {
        config(['billing.v2' => true]);
        $user = $this->owner();
        $this->projects();
        $this->shared($user, 'free');
        $this->rate();
        $publicId = Checkout::open((int) $user->id, 3, 7, 'starter', 'month', NbgRate::latest(), $this->buyer());
        $gel = (string) DB::table('billing_orders')->where('public_id', $publicId)->value('gel_amount');
        $this->assertSame('51.30', number_format((float) $gel, 2, '.', ''));

        $completed = $this->callbackBody($publicId, 'bog-1', 'completed');
        $this->postRaw($completed, $this->sign($completed))->assertOk()->assertJsonPath('duplicate', false);
        $this->postRaw($completed, $this->sign($completed))->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame(1, DB::table('billing_orders')->where('status', 'paid')->count());
        $this->assertSame('starter', Workspace::subscription((int) Workspace::find(3)->id)->plan_code);

        $first = $this->callbackBody($publicId, 'bog-1', 'refunded_partially', null, '10.00', 'part-a');
        $second = $this->callbackBody($publicId, 'bog-1', 'refunded_partially', null, '10.00', 'part-b');
        $this->postRaw($first, $this->sign($first))->assertOk()->assertJsonPath('duplicate', false);
        $this->postRaw($second, $this->sign($second))->assertOk()->assertJsonPath('duplicate', false);
        $this->postRaw($first, $this->sign($first))->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame('20.00', number_format((float) DB::table('billing_orders')->where('public_id', $publicId)->value('refunded_gel'), 2, '.', ''));
        $this->assertSame('paid', DB::table('billing_orders')->where('public_id', $publicId)->value('status'));
        $this->assertSame('starter', Workspace::subscription((int) Workspace::find(3)->id)->plan_code);

        $full = $this->callbackBody($publicId, 'bog-1', 'refunded', null, $gel);
        $this->postRaw($full, $this->sign($full))->assertOk();
        $this->assertSame('refunded', DB::table('billing_orders')->where('public_id', $publicId)->value('status'));
        $this->assertSame('free', Workspace::subscription((int) Workspace::find(3)->id)->plan_code);
    }

    public function test_checkout_without_a_project_explains_instead_of_404(): void
    {
        config(['billing.v2' => true]);
        $user = $this->owner();
        $this->rate();
        DB::table('platform.projects')->where('user_id', 3)->delete();

        Livewire::actingAs($user)->test(Billing::class)
            ->call('checkout', 'starter', 'month')
            ->assertHasErrors(['checkout'])
            ->assertSee('ჯერ შექმენით პროექტი')
            ->assertNoRedirect();
        $this->assertSame(0, DB::table('billing_orders')->count());
    }

    public function test_free_plan_hides_paid_through_and_times_are_tbilisi(): void
    {
        config(['billing.v2' => true]);
        $user = $this->owner();
        $this->projects();
        $this->rate();
        $this->shared($user, 'free');

        Livewire::actingAs($user)->test(Billing::class)
            ->assertDontSee('გადახდილია')
            ->assertSee('(თბილისი)');
    }

    public function test_mock_checkout_still_issues_a_test_invoice(): void
    {
        config(['billing.v2' => true]);
        $user = $this->owner();
        $this->projects();
        $this->rate();

        Livewire::actingAs($user)->test(Billing::class)
            ->assertSee('Starter')
            ->assertSee('Business ჯერ არ იყიდება')
            ->set('projectId', 7)
            ->call('checkout', 'starter', 'month')
            ->assertRedirectContains('/billing/test/');

        $order = DB::table('billing_orders')->first();
        $this->assertSame('mock:'.$order->public_id, $order->bog_order_id);
        $this->assertSame('v2:starter', $order->plan_code);
        $page = URL::temporarySignedRoute('billing.mock.show', now()->addMinutes(5), ['order' => $order->public_id]);
        $this->actingAs($user)->post($page, ['result' => 'success'])->assertRedirect('/account/billing?paid=test');
        $this->assertSame('paid', DB::table('billing_orders')->where('id', $order->id)->value('status'));
        $number = (string) DB::table('billing_invoices')->where('billing_order_id', $order->id)->value('number');
        $this->assertStringStartsWith('TEST-', $number);
        $this->assertSame('starter', Workspace::subscription((int) Workspace::find(3)->id)->plan_code);
    }

    public function test_backfill_preserves_grandfathered_and_legacy_plans_until_rollback(): void
    {
        config(['billing.v2' => true]);
        $grandfathered = $this->owner();
        $this->limits(7, 10000, null, false, null);
        $dry = Backfill::run(true, false);
        $this->assertTrue($dry['ok']);
        $this->assertSame(1, $dry['created']);
        $this->assertSame(1, $dry['grandfathered']);
        $this->assertSame(0, DB::table('billing_workspaces')->count());

        $applied = Backfill::run(false, false);
        $this->assertSame(1, $applied['created']);
        $sub = DB::table('billing_workspace_subscriptions')->first();
        $this->assertSame('legacy-grandfathered', $sub->plan_code);
        $this->assertFalse(Flag::is($sub->shared_quota));
        $this->assertSame(10000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
        $this->artisan('billing:v2-backfill', ['--rollback' => true])->assertOk();
        $this->assertSame(0, DB::table('billing_workspaces')->count());

        $this->limits(7, 2000, 'basic', false, Workspace::plusMonth(Workspace::stamp()));
        $paid = Backfill::run(false, false);
        $this->assertSame(1, $paid['preserved']);
        $kept = DB::table('billing_workspace_subscriptions')->first();
        $this->assertTrue(Flag::is($kept->legacy_preserved));
        $this->assertFalse(Flag::is($kept->shared_quota));
        $this->assertSame(2000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
        $this->assertNull(Workspace::shared(7));

        $both = Backfill::run(true, true);
        $this->assertFalse($both['ok']);
        unset($grandfathered);
    }

    public function test_new_free_users_are_not_backfilled_and_enterprise_is_admin_only(): void
    {
        config(['billing.v2' => true]);
        $this->owner();
        DB::table('platform.users')->where('id', 3)->update(['grandfathered' => 0]);
        $this->limits(7, 100, 'free', false, null);
        $result = Backfill::run(false, false);
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, DB::table('billing_workspaces')->count());

        $this->artisan('billing:v2-assign', [
            'legacyUserId' => 3,
            'plan' => 'enterprise',
            '--quota' => 8000,
            '--projects' => 4,
            '--rpm' => 90,
        ])->assertOk();
        $sub = DB::table('billing_workspace_subscriptions')->first();
        $this->assertSame('enterprise', $sub->plan_code);
        $this->assertSame(90, (int) $sub->rpm);
        $this->assertTrue(Flag::is($sub->shared_quota));
        $this->assertFalse(Catalog::purchasable('business'));
        $this->assertFalse(Catalog::purchasable('enterprise'));

        try {
            Checkout::open((int) User::query()->first()->id, 3, 7, 'business', 'month', $this->rate(), $this->buyer());
            $this->fail('Business stays unsold while its rate limit is disabled.');
        } catch (InvalidArgumentException) {
        }

        try {
            Checkout::open((int) User::query()->first()->id, 3, 8, 'starter', 'month', $this->rate(), $this->buyer());
            $this->fail('Another workspace project must be rejected.');
        } catch (InvalidArgumentException) {
        }
    }

    public function test_api_key_and_widget_share_the_workspace_meter(): void
    {
        config(['billing.v2' => true]);
        $user = $this->owner();
        $this->projects();
        $workspaceId = $this->shared($user, 'starter');
        $this->env('CARAPI_CATALOG_TEST_PROJECT', null);
        $this->env('CARAPI_WIDGET_ENABLED', '1');
        $this->env('CARAPI_PLATFORM_ENABLED', '1');
        MemoryUsageStore::shared()->perMinute = 100;

        $issued = ApiKeys::issue();
        $this->assertTrue((new ProjectRepository($this->platform))->insertKey(3, 7, 'server', $issued['selector'], $issued['hash'], null));
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/brands?limit=1')
            ->assertOk()
            ->assertHeader('X-Quota-Limit', '25000')
            ->assertHeader('X-RateLimit-Limit', '60');
        $rotated = ApiKeys::issue();
        $this->assertTrue((new ProjectRepository($this->platform))->insertKey(3, 7, 'rotated', $rotated['selector'], $rotated['hash'], null));
        $this->assertSame(1, $this->used($workspaceId));
        $this->assertSame(0, (int) (MemoryUsageStore::shared()->previewMonth(7, time())['used'] ?? 0));

        $key = 'geoapi_pk_'.str_repeat('ab', 16);
        DB::table('platform.widgets')->insert([
            'project_id' => 7,
            'enabled' => 1,
            'public_key' => $key,
            'allowed_origins' => json_encode(['https://app.example']),
        ]);
        $this->withHeader('Origin', 'https://app.example')
            ->postJson('/widget/v1/'.$key.'/brands', [])
            ->assertOk();
        $this->assertSame(2, $this->used($workspaceId));
        $this->assertSame(2, $this->projectUsed($workspaceId, 7));
    }

    public function test_six_processes_cannot_spend_the_last_unit_twice(): void
    {
        $path = sys_get_temp_dir().'/geoapi-v2-'.bin2hex(random_bytes(4)).'.sqlite';
        touch($path);
        $init = proc_open(
            [PHP_BINARY, base_path('tests/bin/billing-v2-hit.php'), $path, 'init'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
        );
        $this->assertIsResource($init);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($init), $error."\n".$output);
        $decoded = json_decode((string) $output, true);
        $this->assertIsArray($decoded, (string) $output."\n".$error);
        $workspaceId = (int) $decoded['workspace'];

        $procs = [];
        try {
            for ($i = 0; $i < 6; $i++) {
                $childPipes = [];
                $process = proc_open(
                    [PHP_BINARY, base_path('tests/bin/billing-v2-hit.php'), $path, 'hit', (string) $workspaceId],
                    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $childPipes,
                    base_path(),
                );
                $this->assertIsResource($process);
                $procs[] = ['process' => $process, 'pipes' => $childPipes];
            }
            $oks = 0;
            foreach ($procs as $proc) {
                $childOut = stream_get_contents($proc['pipes'][1]);
                $childErr = stream_get_contents($proc['pipes'][2]);
                fclose($proc['pipes'][1]);
                fclose($proc['pipes'][2]);
                $this->assertSame(0, proc_close($proc['process']), $childErr."\n".$childOut);
                $row = json_decode((string) $childOut, true);
                $this->assertIsArray($row, (string) $childOut);
                $oks += ! empty($row['ok']) ? 1 : 0;
            }
            $this->assertSame(1, $oks);
            $pdo = new PDO('sqlite:'.$path);
            $used = (int) $pdo->query('SELECT used_count FROM billing_period_usage')->fetchColumn();
            $this->assertSame(1, $used);
        } finally {
            foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function test_account_pages_show_shared_usage_and_a_cap_field(): void
    {
        config(['billing.v2' => true]);
        $user = $this->owner();
        $this->projects();
        $this->shared($user, 'growth');
        $this->rate();

        Livewire::actingAs($user)->test(Projects::class)
            ->set('projectId', 7)
            ->assertSee('data-v2-cap', false)
            ->set('capInput', '50')
            ->call('setCap')
            ->assertHasNoErrors();

        $this->assertSame(50, (int) DB::table('billing_project_caps')->where('project_id', 7)->value('monthly_cap'));
    }

    /** @param list<array<string, mixed>> $rows */
    private function section(array $rows, string $id): array
    {
        foreach ($rows as $row) {
            if (($row['id'] ?? '') === $id) {
                return $row['paragraphs'];
            }
        }
        $this->fail('Missing '.$id);
    }

    private function platformSchema(): void
    {
        foreach ([
            'platform.widgets',
            'platform.email_tokens',
            'platform.rate_windows',
            'platform.daily_usage',
            'platform.monthly_usage',
            'platform.api_keys',
            'platform.project_limits',
            'platform.projects',
            'platform.users',
        ] as $table) {
            DB::statement('DROP TABLE IF EXISTS '.$table);
        }
        foreach ([
            "CREATE TABLE platform.users (id integer primary key, first_name text, last_name text, email text, password_hash text, status text not null default 'active', email_verified_at text, grandfathered integer not null default 1)",
            "CREATE TABLE platform.projects (id integer primary key, user_id integer not null, name text, website_url text, status text not null default 'active', created_at text, updated_at text)",
            'CREATE TABLE platform.api_keys (id integer primary key, project_id integer not null, name text, key_selector text not null, secret_hash text not null, scope text not null default \'catalog:read\', created_at text, expires_at text, revoked_at text, last_used_at text)',
            'CREATE TABLE platform.project_limits (project_id integer primary key, per_minute integer not null, monthly_quota integer not null, per_day integer not null, plan_code text, plan_ends_at text, unlimited_month integer not null default 0)',
            'CREATE TABLE platform.monthly_usage (project_id integer not null, period text not null, used_count integer not null, primary key (project_id, period))',
            'CREATE TABLE platform.daily_usage (project_id integer not null, day text not null, route text not null, used_count integer not null, primary key (project_id, day, route))',
            'CREATE TABLE platform.rate_windows (scope text not null, subject text not null, window_start text not null, hit_count integer not null, primary key (scope, subject, window_start))',
            'CREATE TABLE platform.widgets (project_id integer primary key, enabled integer not null default 1, public_key text not null, allowed_origins text not null default \'[]\', updated_at text)',
        ] as $sql) {
            DB::statement($sql);
        }
        DB::table('platform.users')->insert([
            ['id' => 3, 'first_name' => 'Nino', 'last_name' => 'Beridze', 'email' => 'owner@example.com', 'password_hash' => 'x', 'status' => 'active', 'email_verified_at' => '2026-01-01 00:00:00', 'grandfathered' => 1],
            ['id' => 4, 'first_name' => 'Ana', 'last_name' => 'Other', 'email' => 'other@example.com', 'password_hash' => 'x', 'status' => 'active', 'email_verified_at' => '2026-01-01 00:00:00', 'grandfathered' => 1],
        ]);
        DB::table('platform.projects')->insert([
            ['id' => 7, 'user_id' => 3, 'name' => 'Catalog', 'website_url' => 'https://owner.example', 'status' => 'active'],
            ['id' => 8, 'user_id' => 4, 'name' => 'Other', 'website_url' => 'https://other.example', 'status' => 'active'],
        ]);
    }

    private function projects(): void
    {
        DB::table('platform.projects')->updateOrInsert(
            ['id' => 11],
            ['user_id' => 3, 'name' => 'Second', 'website_url' => 'https://two.example', 'status' => 'active'],
        );
        DB::table('platform.projects')->updateOrInsert(
            ['id' => 12],
            ['user_id' => 3, 'name' => 'Third', 'website_url' => 'https://three.example', 'status' => 'active'],
        );
    }

    private function owner(): User
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'email_verified_at' => now(),
        ]);
        $user->forceFill([
            'legacy_platform_user_id' => 3,
            'buyer_kind' => 'legal',
            'company_name' => 'Test LLC',
            'company_code' => '405123456',
            'company_address' => 'Tbilisi Rustaveli 1',
            'vat_payer' => true,
        ])->save();

        return $user;
    }

    private function shared(User $user, string $plan): int
    {
        $spec = Catalog::plans()[$plan];
        $now = Workspace::stamp();

        return Workspace::create((int) $user->id, 3, 'signup', [
            'plan_code' => $plan,
            'interval' => 'month',
            'monthly_quota' => $spec['quota'],
            'rpm' => Catalog::rpm($plan),
            'included_projects' => $spec['projects'],
            'period_start' => $now,
            'period_end' => Workspace::plusMonth($now),
            'term_end' => Workspace::plusMonth($now),
            'shared_quota' => true,
            'source' => 'test',
        ]);
    }

    private function used(int $workspaceId): int
    {
        return (int) DB::table('billing_period_usage')->where('workspace_id', $workspaceId)->sum('used_count');
    }

    private function projectUsed(int $workspaceId, int $projectId): int
    {
        return (int) DB::table('billing_project_period_usage')
            ->where('workspace_id', $workspaceId)
            ->where('project_id', $projectId)
            ->sum('used_count');
    }

    private function pay(User $user, string $product, string $interval, int $projectId = 7): void
    {
        $this->rate();
        $publicId = Checkout::open((int) $user->id, 3, $projectId, $product, $interval, NbgRate::latest(), $this->buyer());
        Entitlements::grant($publicId);
    }

    /** @return array{kind:string, name:string, company_name:string, company_code:string, company_address:string, vat_payer:bool} */
    private function buyer(): array
    {
        return [
            'kind' => 'legal',
            'name' => 'ნინო ბერიძე',
            'company_name' => 'Test LLC',
            'company_code' => '405123456',
            'company_address' => 'Tbilisi Rustaveli 1',
            'vat_payer' => true,
        ];
    }

    /** @return array{code:string, rate:string, quantity:int, rated_on:string} */
    private function rate(): array
    {
        NbgRate::remember(NbgRate::parse((string) file_get_contents(base_path('tests/fixtures/nbg-usd.json'))));

        return NbgRate::latest();
    }

    private function limits(int $projectId, int $quota, ?string $plan, bool $unlimited, ?string $ends): void
    {
        DB::table('platform.project_limits')->updateOrInsert(['project_id' => $projectId], [
            'per_minute' => 60,
            'monthly_quota' => $quota,
            'per_day' => 2000,
            'plan_code' => $plan,
            'plan_ends_at' => $ends,
            'unlimited_month' => $unlimited ? 1 : 0,
        ]);
    }

    private function callbackBody(string $publicId, string $bogId, string $status, ?string $amount = null, ?string $refundAmount = null, ?string $refundId = null): string
    {
        $amount ??= number_format((float) DB::table('billing_orders')->where('public_id', $publicId)->value('gel_amount'), 2, '.', '');
        $units = ['request_amount' => $amount, 'transfer_amount' => $amount, 'currency_code' => 'GEL'];
        if ($refundAmount !== null) {
            $units['refund_amount'] = $refundAmount;
        }
        $body = [
            'order_id' => $bogId,
            'external_order_id' => $publicId,
            'order_status' => ['key' => $status, 'value' => $status],
            'purchase_units' => $units,
        ];
        if ($refundId !== null) {
            $body['refund_id'] = $refundId;
        }

        return json_encode([
            'event' => 'order_payment',
            'zoned_request_time' => '2026-10-21T12:00:00.000000Z',
            'body' => $body,
        ], JSON_UNESCAPED_SLASHES);
    }

    private function sign(string $body): string
    {
        openssl_sign($body, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    private function postRaw(string $body, string $signature): TestResponse
    {
        return $this->call('POST', '/billing/bog/callback', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CALLBACK_SIGNATURE' => $signature,
        ], $body);
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
