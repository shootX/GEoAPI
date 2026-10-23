<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Billing\Plans;
use App\Billing\V2\Catalog;
use App\Livewire\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Production guest audit 2026-10-23: public copy states what checkout charges and what the API serves. */
final class PublicCopyMatchesCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['billing.v2' => true]);
    }

    public function test_pricing_and_terms_quote_the_pack_and_extra_project_prices_checkout_charges(): void
    {
        $starter = Plans::lari(Catalog::packs()['starter']['tetri']);
        $growth = Plans::lari(Catalog::packs()['growth']['tetri']);
        $extra = Plans::lari(Catalog::EXTRA_PROJECT_TETRI);

        foreach (['en', 'ka'] as $locale) {
            foreach (['/pricing', '/terms'] as $path) {
                $html = (string) $this->withSession(['locale' => $locale])->get($path)->assertOk()
                    ->assertSee($starter)->assertSee($growth)->assertSee($extra)
                    ->getContent();
                // The old hard-coded 9 ₾ / 20 ₾ / 5 ₾ (159 ₾ must not match).
                foreach ([900, 2000, 500] as $old) {
                    $this->assertDoesNotMatchRegularExpression('/(?<![\d.,])'.preg_quote(Plans::lari($old), '/').'/u', $html, $path.' '.$locale);
                }
            }
        }
    }

    public function test_terms_list_the_specs_request(): void
    {
        $this->withSession(['locale' => 'en'])->get('/terms')->assertOk()
            ->assertSee('/modifications/{id}/specs')
            ->assertDontSee('There is no separate technical-spec request.');
        $this->withSession(['locale' => 'ka'])->get('/terms')->assertOk()
            ->assertSee('/modifications/{id}/specs')
            ->assertDontSee('ტექნიკური მახასიათებლების ცალკე მოთხოვნა არ არის');
    }

    public function test_pay_buttons_carry_the_plan_they_belong_to(): void
    {
        $this->withSession(['locale' => 'en'])->get('/pricing')->assertOk()
            ->assertSee('/account/billing?plan=starter', false)
            ->assertSee('/account/billing?plan=growth', false);
    }

    public function test_home_sample_shows_free_limits_and_the_coverage_link_opens_coverage_docs(): void
    {
        $this->withSession(['locale' => 'en'])->get('/')->assertOk()
            ->assertSee('X-RateLimit-Limit: '.Catalog::rpm('free').' · X-Quota-Limit: '.Catalog::plans()['free']['quota'])
            ->assertDontSee('X-Quota-Limit: 25000')
            ->assertSee('/api/v1/docs#tag/Catalog/operation/getCoverage', false)
            ->assertSee('photos · demo only');
    }

    public function test_headings_do_not_skip_a_level(): void
    {
        foreach (['/', '/pricing'] as $path) {
            $html = $this->withSession(['locale' => 'en'])->get($path)->assertOk()->getContent();
            preg_match_all('/<h([1-6])\b/', (string) $html, $m);
            $previous = 0;
            foreach (array_map('intval', $m[1]) as $level) {
                $this->assertLessThanOrEqual($previous + 1, $level, $path.': h'.$previous.' is followed by h'.$level);
                $previous = $level;
            }
        }
    }

    public function test_a_login_error_is_tied_to_its_field_and_announced(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'qa-audit-does-not-exist@example.com')
            ->set('password', 'Wrong-password-1')
            ->call('login')
            ->assertHasErrors(['email'])
            ->assertSeeHtml('aria-invalid="true" aria-describedby="login-email-error"')
            ->assertSeeHtml('id="login-email-error" role="alert"');
    }
}
