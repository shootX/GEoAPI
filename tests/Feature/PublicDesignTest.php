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

    public function test_pricing_shows_free_and_upcoming_pro_without_a_price(): void
    {
        $this->withSession(['locale' => 'ka'])
            ->get('/pricing')
            ->assertOk()
            ->assertSee('Free')
            ->assertSee('Pro — მალე')
            ->assertSee('10 000')
            ->assertDontSee('Stripe');
    }

    public function test_home_georgian_copy_uses_the_picker_word(): void
    {
        $this->withSession(['locale' => 'ka'])
            ->get('/')
            ->assertOk()
            ->assertSee('ავტომობილების მონაცემები')
            ->assertSee('API ან მზა ვიჯეტი')
            ->assertSee('მზა არჩევანი')
            ->assertDontSee('ამრჩევი')
            ->assertSee('22 367', false);
    }

    public function test_demo_and_guide_render_the_public_sections(): void
    {
        $this->withSession(['locale' => 'ka'])
            ->get('/demo')
            ->assertOk()
            ->assertSee('ავტომობილის არჩევა')
            ->assertSee('ჩასასმელი კოდი')
            ->assertSee('კოპირება')
            ->assertSee('vp-response', false);

        $this->withSession(['locale' => 'ka'])
            ->get('/guide')
            ->assertOk()
            ->assertSee('curl')
            ->assertSee('JavaScript')
            ->assertSee('PHP')
            ->assertSee('ბრენდები');
    }
}
