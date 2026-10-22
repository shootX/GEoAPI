<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class PublicDesignTest extends TestCase
{
    public function test_locale_prefixes_set_the_session_and_redirect_home(): void
    {
        $this->get('/en')->assertRedirect('/')->assertSessionHas('locale', 'en');
        $this->get('/ka')->assertRedirect('/')->assertSessionHas('locale', 'ka');
    }

    public function test_pricing_shows_free_basic_and_premium_with_bog_checkout(): void
    {
        $this->withSession(['locale' => 'ka'])
            ->get('/pricing')
            ->assertOk()
            ->assertSee('Free')
            ->assertSee('Basic')
            ->assertSee('Premium')
            ->assertSee('₾')
            ->assertDontSee('$59')
            ->assertDontSee('$99')
            ->assertDontSee('USD')
            ->assertSee('10 000')
            ->assertSee('Bank of Georgia')
            ->assertDontSee('Stripe');
    }

    public function test_home_georgian_copy_uses_the_picker_word(): void
    {
        $dir = base_path('legacy/cache');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $path = $dir.'/public-counts.json';
        file_put_contents($path, json_encode([
            'status' => 'ok',
            'brands' => 314,
            'models' => 4710,
            'generations' => 22367,
            'generation_groups' => 10228,
            'models_without_generations' => 1,
            'modifications' => 343640,
            'photos' => 634,
        ]));
        $_ENV['CARAPI_CATALOG_ENABLED'] = '1';
        $_SERVER['CARAPI_CATALOG_ENABLED'] = '1';

        try {
            $this->withSession(['locale' => 'ka'])
                ->get('/')
                ->assertOk()
                ->assertSee('ბრენდები, მოდელები, თაობები და სპეციფიკაციები')
                ->assertSee('ერთი API-ით')
                ->assertSee('ჩასაშენებელი ვიჯეტი')
                ->assertDontSee('ამრჩევი')
                ->assertSee('314', false)
                ->assertSee('4 710', false)
                ->assertSee('22 367', false)
                ->assertSee('343 640', false)
                ->assertDontSee('10 228', false)
                ->assertDontSee('ინტეგრაციები');
        } finally {
            unset($_ENV['CARAPI_CATALOG_ENABLED'], $_SERVER['CARAPI_CATALOG_ENABLED']);
            @unlink($path);
        }
    }

    public function test_home_shows_specs_markets_equipment_and_photos(): void
    {
        $dir = base_path('legacy/cache');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $path = $dir.'/public-counts.json';
        file_put_contents($path, json_encode([
            'status' => 'ok', 'brands' => 314, 'models' => 4710, 'generations' => 22367, 'generation_groups' => 10228,
            'models_without_generations' => 1, 'modifications' => 420021, 'photos' => 573,
            'specifications' => 420021, 'markets' => 3070, 'equipment' => 47312000,
        ]));
        $_ENV['CARAPI_CATALOG_ENABLED'] = '1';
        $_SERVER['CARAPI_CATALOG_ENABLED'] = '1';

        try {
            $this->withSession(['locale' => 'ka'])->get('/')->assertOk()
                ->assertSee('3 070', false)->assertSee('47 მლნ+')->assertSee('კომპლექტაციის')->assertSee('573', false)->assertSee('სპეციფიკაცია ·');
            $this->withSession(['locale' => 'en'])->get('/')->assertOk()
                ->assertSee('47M+')->assertSee('trim/equipment')->assertSee('photos')->assertDontSee('AI photo');
        } finally {
            unset($_ENV['CARAPI_CATALOG_ENABLED'], $_SERVER['CARAPI_CATALOG_ENABLED']);
            @unlink($path);
        }
    }

    public function test_demo_vehicle_title_is_localized(): void
    {
        $this->withSession(['locale' => 'en'])->get('/demo')->assertOk()
            ->assertSee('<h2 id="vp-title">Vehicle</h2>', false);
        $this->withSession(['locale' => 'ka'])->get('/demo')->assertOk()
            ->assertSee('<h2 id="vp-title">ავტომობილი</h2>', false);
    }

    public function test_demo_and_guide_render_the_public_sections(): void
    {
        $this->withSession(['locale' => 'ka'])
            ->get('/demo')
            ->assertOk()
            ->assertSee('ავტომობილის არჩევა')
            ->assertSee('ჩასასმელი კოდი')
            ->assertSee('კოპირება')
            ->assertSee('vp-response', false)
            ->assertSee('კომპლექტაცია');

        $this->withSession(['locale' => 'ka'])
            ->get('/guide')
            ->assertOk()
            ->assertSee('curl')
            ->assertSee('JavaScript')
            ->assertSee('PHP')
            ->assertSee('ბრენდები');
    }

    public function test_new_design_layout_assets_and_auth_split_screen(): void
    {
        $home = (string) $this->withSession(['locale' => 'ka'])->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('class="site ga', $home);
        $this->assertStringContainsString('assets/brand/geoapi-lockup-light.svg', $home);
        $this->assertStringContainsString('assets/brand/og-image-ka.png', $home);
        $this->assertStringContainsString('hello@geoapi.ge', $home);
        $this->assertStringNotContainsString('public-design.css', $home);
        $this->assertFileExists(public_path('assets/brand/og-image-en.png'));
        $this->assertFileExists(public_path('assets/brand/favicon.svg'));
        $this->assertFileExists(resource_path('fonts/jetbrains-mono-400.woff2'));

        $this->withSession(['locale' => 'ka'])->get('/login')->assertOk()
            ->assertSee('class="auth"', false)
            ->assertSee('ერთი API-ით')
            ->assertSee('wire:model="email"', false);

        $this->withSession(['locale' => 'ka'])->get('/register')->assertOk()
            ->assertSee('class="auth"', false)
            ->assertSee('value="legal"', false)
            ->assertSee('wire:model="accept_terms"', false);

        $this->withSession(['locale' => 'ka'])->get('/forgot-password')->assertOk()
            ->assertSee('class="auth"', false)
            ->assertSee('wire:submit="sendResetLink"', false);
    }
}
