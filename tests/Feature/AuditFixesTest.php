<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_and_private_cache_on_public_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");

        $guide = $this->get('/guide')->assertOk()->assertHeader('Vary', 'Cookie');
        $cache = (string) $guide->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cache);
        $this->assertStringContainsString('max-age=60', $cache);
    }

    public function test_admin_api_does_not_send_a_wildcard_cors_header(): void
    {
        $this->withHeader('Origin', 'https://evil.example')
            ->getJson('/api/admin/me')
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_hidden_docs_are_json_without_a_stack_trace(): void
    {
        $this->get('/api/docs')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found')
            ->assertDontSee('vendor/', false);

        $this->get('/api/docs/openapi.json')
            ->assertNotFound()
            ->assertJsonPath('error.message', 'Not found.');
    }

    public function test_public_openapi_and_sitemap_are_available(): void
    {
        $this->get('/api/v1/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('info.title', 'CarAPI');

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('/guide', false);

        $this->get('/docs')->assertRedirect('/guide');

        $robots = (string) file_get_contents(public_path('robots.txt'));
        $this->assertStringContainsString('Sitemap: https://car.socialsave.cc/sitemap.xml', $robots);
    }

    public function test_english_privacy_and_georgian_password_reset_copy(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/privacy')
            ->assertOk()
            ->assertSee('Privacy policy')
            ->assertDontSee('კონფიდენციალურობის პოლიტიკა');

        $this->withSession(['locale' => 'ka'])->get('/forgot-password')->assertOk();
        $this->assertSame(
            'პაროლის აღდგენის ბმული ელფოსტაზე გაიგზავნა.',
            __('passwords.sent'),
        );
    }

    public function test_unknown_pages_use_the_branded_not_found_view(): void
    {
        $this->get('/no-such-public-page')
            ->assertNotFound()
            ->assertSee('CarAPI', false)
            ->assertDontSee('vendor/', false);
    }

    public function test_widget_embed_uses_the_demo_key(): void
    {
        $this->get('/guide')
            ->assertOk()
            ->assertSee('public-key=&quot;demo&quot;', false)
            ->assertDontSee('carapi_pk_example', false);

        $script = (string) file_get_contents(public_path('widget/v1/carapi-filter.js'));
        $this->assertStringContainsString('generation_id: this.state.generation', $script);
    }
}
