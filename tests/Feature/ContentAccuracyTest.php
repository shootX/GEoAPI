<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Billing\V2\Catalog;
use App\Catalog\PublicSpec;
use Tests\TestCase;

/** QA GEO-QA-012, 013, 014, 018: public copy matches the live billing rules and is accessible. */
final class ContentAccuracyTest extends TestCase
{
    public function test_home_faq_states_the_v2_limits_and_the_shared_counter(): void
    {
        config(['billing.v2' => true]);
        $rpm = (string) Catalog::rpm('free');

        $this->withSession(['locale' => 'en'])->get('/')->assertOk()
            ->assertSee('Free: '.$rpm.' requests a minute')
            ->assertSee('one counter shared by all projects in your account')
            ->assertSee('a downgrade starts when the paid term ends')
            ->assertDontSee('Above 60 requests a minute')
            ->assertDontSee('Change the plan any time');

        $this->withSession(['locale' => 'ka'])->get('/')->assertOk()
            ->assertSee('Free — წუთში '.$rpm.' მოთხოვნა')
            ->assertDontSee('წუთში 60 მოთხოვნის გადაჭარბებისას')
            ->assertDontSee('გეგმის შეცვლა ნებისმიერ დროს');
    }

    public function test_georgian_pricing_has_a_georgian_meta_description(): void
    {
        config(['billing.v2' => true]);

        $this->withSession(['locale' => 'ka'])->get('/pricing')->assertOk()
            ->assertSee('<meta name="description" content="Free, Starter და Growth. ფასები დღგ-ს შეიცავს.', false);
    }

    public function test_the_browser_javascript_sample_warns_that_the_secret_key_is_server_only(): void
    {
        $samples = PublicSpec::codeSamples('/api/v1/brands');

        $this->assertStringContainsString('Server-side only', $samples['Node.js']);
    }

    public function test_guide_code_tabs_are_real_aria_tabs_and_code_is_keyboard_scrollable(): void
    {
        $html = (string) $this->withSession(['locale' => 'ka'])->get('/guide')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button type="button" role="tab" id="(cs-[a-z0-9]+-t0)" aria-controls="cs-[a-z0-9]+-p0"/', $html);
        $this->assertMatchesRegularExpression('/<pre role="tabpanel" id="cs-[a-z0-9]+-p0" aria-labelledby="cs-[a-z0-9]+-t0" tabindex="0"/', $html);
        $this->assertStringNotContainsString('იქ არ დებს', $html);
    }
}
