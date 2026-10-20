<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ComingSoonSignup;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ComingSoonTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_stay_open_when_coming_soon_is_false(): void
    {
        $this->assertFalse(Setting::query()->find('coming_soon')?->value);

        $this->get('/')->assertOk();
    }

    public function test_public_page_is_held_and_admin_api_stays_open(): void
    {
        $this->enable();

        $this->get('/')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '86400')
            ->assertSee('მალე', false)
            ->assertSee('logo.png', false);

        $this->withSession(['locale' => 'en'])
            ->get('/')
            ->assertStatus(503)
            ->assertSee('Coming soon', false);

        $this->get('/login')->assertOk();
        $this->get('/api/docs')->assertNotFound();
        $this->getJson('/api/admin/me')->assertUnauthorized();
        $this->getJson('/api/v1/brands')->assertStatus(503)->assertJsonPath('error', 'coming_soon');
    }

    public function test_preview_link_sets_a_cookie_and_admins_pass(): void
    {
        $this->enable();
        config(['coming_soon.preview_secret' => 'preview-secret']);

        $this->get('/?preview=wrong')->assertStatus(503);

        $granted = $this->get('/?preview=preview-secret');
        $granted->assertOk();
        $cookie = $granted->getCookie('carapi_preview');
        $this->assertNotNull($cookie);

        $this->withCookie('carapi_preview', $cookie->getValue())
            ->get('/guide')
            ->assertOk();
        $this->defaultCookies = [];

        $admin = User::factory()->create(['email' => 'admin@example.com']);
        config(['electrik.operators.emails' => ['admin@example.com']]);
        $this->actingAs($admin)->get('/privacy')->assertOk();

        $member = User::factory()->create(['email' => 'member@example.com']);
        $this->actingAs($member)->get('/terms')->assertStatus(503);
    }

    public function test_admin_can_turn_coming_soon_off(): void
    {
        $this->enable();
        $admin = User::factory()->create(['email' => 'panel@example.com']);
        config(['electrik.operators.emails' => ['panel@example.com']]);

        $this->actingAs($admin)
            ->from('/admin')
            ->post('/admin/coming-soon')
            ->assertRedirect('/admin');

        $this->assertSame(false, Setting::query()->find('coming_soon')?->value);
    }

    public function test_other_users_cannot_turn_coming_soon_off(): void
    {
        $member = User::factory()->create(['email' => 'member@example.com']);

        $this->actingAs($member)
            ->post('/admin/coming-soon')
            ->assertNotFound();

        $this->assertSame(false, Setting::query()->find('coming_soon')?->value);
    }

    public function test_locale_prefixes_redirect_while_the_gate_is_closed(): void
    {
        $this->enable();

        $this->get('/en')->assertRedirect('/')->assertSessionHas('locale', 'en');
        $this->get('/ka')->assertRedirect('/')->assertSessionHas('locale', 'ka');
    }

    public function test_email_is_stored(): void
    {
        $this->enable();

        $this->followingRedirects()
            ->post('/coming-soon', ['email' => 'Soon@Example.com'])
            ->assertStatus(503)
            ->assertSee('მადლობა', false);

        $this->assertTrue(ComingSoonSignup::query()->where('email', 'soon@example.com')->exists());

        $this->post('/coming-soon', ['email' => 'not-an-email'])->assertSessionHasErrors('email');
    }

    private function enable(): void
    {
        $row = Setting::query()->find('coming_soon');
        $this->assertInstanceOf(Setting::class, $row);
        $row->value = true;
        $row->save();
    }
}
