<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Geoapi\Csp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** QA GEO-QA-022: nonce-based CSP without unsafe-inline, unsafe-eval or third-party script hosts. */
final class StrictCspTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function pages(): array
    {
        return [
            'home' => ['/'],
            'pricing' => ['/pricing'],
            'guide' => ['/guide'],
            'demo' => ['/demo'],
            'terms' => ['/terms'],
            'blog' => ['/blog'],
            'login' => ['/login'],
            'register' => ['/register'],
            'forgot password' => ['/forgot-password'],
            'api reference' => ['/api/v1/docs'],
        ];
    }

    #[DataProvider('pages')]
    public function test_public_pages_send_the_strict_policy_and_nonce_every_inline_block(string $path): void
    {
        $this->assertStrictPage($this->get($path));
    }

    public function test_cabinet_pages_and_livewire_use_the_csp_safe_build(): void
    {
        $user = User::factory()->create();
        foreach (['/account/billing'] as $path) {
            $response = $this->actingAs($user)->get($path);
            $this->assertStrictPage($response);
            $this->assertMatchesRegularExpression('#livewire[^"]*\.csp(\.min)?\.js#', (string) $response->getContent(), $path.' loads Livewire\'s CSP-safe Alpine build');
        }
    }

    public function test_redoc_gets_the_nonce_instead_of_inline_styles(): void
    {
        // QA retest L1: the API reference sent style-src 'unsafe-inline'.
        $response = $this->get('/api/v1/docs');

        $this->assertStringContainsString("nonce: '".$this->nonce($response)."'", (string) $response->getContent());
        $this->assertMatchesRegularExpression("/style-src 'self' 'nonce-[^;]*;/", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertDoesNotMatchRegularExpression("/style-src [^;]*'unsafe-inline'/", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_pages_without_the_ui_kit_refuse_style_attributes(): void
    {
        // QA retest L3: style-src-attr allowed 'unsafe-inline' on every page.
        $user = User::factory()->create();
        foreach (['/', '/pricing', '/guide', '/demo', '/terms', '/blog', '/api/v1/docs'] as $path) {
            $this->assertStringContainsString("style-src-attr 'none'", (string) $this->get($path)->headers->get('Content-Security-Policy'), $path);
        }
        $this->assertStringContainsString("style-src-attr 'none'", (string) $this->actingAs($user)->get('/account/billing')->headers->get('Content-Security-Policy'));
    }

    public function test_ui_kit_views_with_style_attributes_relax_style_src_attr(): void
    {
        $this->assertStringContainsString("style-src-attr 'none'", SecurityHeaders::policy('n'));
        $this->assertStringContainsString("style-src-attr 'unsafe-inline'", SecurityHeaders::policy('n', styleAttributes: true));

        // Every UI-kit view that writes a style attribute must be listed, or its page breaks
        // when the kit is upgraded.
        $found = [];
        foreach (['slate' => 'vendor/electrik/slate/resources/views', 'electrik' => 'vendor/electrik/electrik/resources/views'] as $namespace => $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (preg_match("/\\sstyle=|:style=|x-bind:style|'style'/", (string) file_get_contents($file->getPathname())) === 1) {
                    $found[] = $namespace.'::'.str_replace('/', '.', substr($file->getPathname(), strlen(base_path($dir)) + 1, -strlen('.blade.php')));
                }
            }
        }
        sort($found);
        $this->assertSame($found, SecurityHeaders::STYLE_ATTRIBUTE_VIEWS);
    }

    public function test_project_views_and_legacy_pages_have_no_style_attributes(): void
    {
        $files = ['legacy/front.php', 'legacy/src/Platform/View.php', 'legacy/src/SiteChrome.php'];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)) as $file) {
            $files[] = substr($file->getPathname(), strlen(base_path()) + 1);
        }
        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression('/\sstyle=/', (string) file_get_contents(base_path($file)), $file.' has a style attribute; use a class (resources/css/geoapi-inline.css)');
        }
    }

    public function test_each_request_gets_a_new_nonce(): void
    {
        $this->assertNotSame($this->nonce($this->get('/')), $this->nonce($this->get('/')));
    }

    public function test_the_policy_blocks_framing_plugins_and_base_tags(): void
    {
        $policy = (string) $this->get('/')->headers->get('Content-Security-Policy');

        foreach (["default-src 'none'", "object-src 'none'", "base-uri 'none'", "frame-ancestors 'none'", "form-action 'self'", "script-src-attr 'none'", "img-src 'self' data:", "connect-src 'self'"] as $directive) {
            $this->assertStringContainsString($directive, $policy);
        }
        $this->get('/')->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_legacy_inline_blocks_carry_the_request_nonce(): void
    {
        $nonce = Vite::useCspNonce();
        $this->assertSame(' nonce="'.$nonce.'"', Csp::attr());

        foreach (['legacy/front.php', 'legacy/src/Platform/View.php', 'legacy/src/Platform/Http.php', 'legacy/src/SiteChrome.php', 'legacy/src/Auth/View.php'] as $file) {
            $source = (string) file_get_contents(base_path($file));
            $this->assertDoesNotMatchRegularExpression('/<(script|style)>/', $source, $file.' has an inline block without Csp::attr()');
            $this->assertDoesNotMatchRegularExpression('/\son(click|change|load|error|submit|input)=/', $source, $file.' has an inline event handler');
        }
    }

    private function assertStrictPage(TestResponse $response): void
    {
        $response->assertOk();
        $policy = (string) $response->headers->get('Content-Security-Policy');
        $nonce = $this->nonce($response);
        $html = (string) $response->getContent();

        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+';/", $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $this->assertStringNotContainsString('unpkg.com', $policy);
        $this->assertStringNotContainsString('redoc.ly', $policy);
        $this->assertStringNotContainsString('https:', $policy, 'no wildcard or third-party hosts');
        $this->assertStringContainsString("style-src 'self' 'nonce-".$nonce."'", $policy);
        $scriptSrc = (string) preg_replace('/^.*?script-src ([^;]*);.*$/', '$1', $policy);
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc);

        preg_match_all('/<(script|style)\b([^>]*)>/i', $html, $tags, PREG_SET_ORDER);
        foreach ($tags as [$tag, $name, $attributes]) {
            if (preg_match('/\bsrc=|type="application\/(ld\+)?json"/', $attributes) === 1) {
                $this->assertDoesNotMatchRegularExpression('#src="(https?:)?//(?!localhost/)#', $attributes, 'scripts load from this origin: '.$tag);

                continue;
            }
            $this->assertStringContainsString('nonce="'.$nonce.'"', $attributes, 'inline <'.$name.'> without the nonce: '.$tag);
        }
        if (str_contains($policy, "style-src-attr 'none'")) {
            $this->assertDoesNotMatchRegularExpression('/<[a-z][^>]*\sstyle\s*=/i', $html, 'style attributes are blocked by the policy');
        }
        $this->assertDoesNotMatchRegularExpression('/<[a-z][^>]*\son(click|change|load|error|submit|input|keydown|mouseover)\s*=/i', $html, 'inline event handlers are blocked by the policy');
    }

    private function nonce(TestResponse $response): string
    {
        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertSame(1, preg_match("/'nonce-([^']+)'/", $policy, $match));

        return $match[1];
    }
}
