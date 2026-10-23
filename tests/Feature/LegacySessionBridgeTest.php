<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Geoapi\Platform\Session;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * QA retest H1: the legacy geoapi_platform session must end on logout and honour its idle/absolute limits.
 * The bridge runs in the web group after Authenticate, so the tests reach it through the public "/".
 */
final class LegacySessionBridgeTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            DB::statement("ATTACH DATABASE ':memory:' AS platform");
        } catch (\Throwable) {
        }
        DB::statement('DROP TABLE IF EXISTS platform.users');
        DB::statement("CREATE TABLE platform.users (id integer primary key, email text, status text not null default 'active', email_verified_at text, auth_version integer not null default 1)");
        DB::table('platform.users')->insert(['id' => 70, 'email' => 'bridge@example.com', 'status' => 'active', 'auth_version' => 1]);
        User::factory()->create(['email' => 'bridge@example.com', 'legacy_platform_user_id' => 70]);
        $this->dir = sys_get_temp_dir().'/geoapi-bridge-'.bin2hex(random_bytes(4));
        mkdir($this->dir, 0700, true);
        $this->setEnv('GEOAPI_PLATFORM_SESSION_PATH', $this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/sess_*') ?: []);
        @rmdir($this->dir);
        $this->setEnv('GEOAPI_PLATFORM_SESSION_PATH', null);
        parent::tearDown();
    }

    private function setEnv(string $name, ?string $value): void
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

    private function legacySession(int $issuedAgo, int $seenAgo): string
    {
        $sid = bin2hex(random_bytes(16));
        $now = time();
        file_put_contents($this->dir.'/sess_'.$sid, 'user_id|i:70;auth_version|i:1;issued_at|i:'.($now - $issuedAgo).';last_seen|i:'.($now - $seenAgo).';');

        return $sid;
    }

    public function test_a_fresh_legacy_session_signs_the_user_in(): void
    {
        $this->withUnencryptedCookie('geoapi_platform', $this->legacySession(60, 60))->get('/');

        $this->assertAuthenticated();
    }

    public function test_an_idle_legacy_session_is_refused_and_removed(): void
    {
        $sid = $this->legacySession(3600, 3600);

        $this->withUnencryptedCookie('geoapi_platform', $sid)->get('/');

        $this->assertGuest();
        $this->assertFileDoesNotExist($this->dir.'/sess_'.$sid);
    }

    public function test_a_legacy_session_past_its_absolute_limit_is_refused(): void
    {
        $this->withUnencryptedCookie('geoapi_platform', $this->legacySession(13 * 3600, 60))->get('/');

        $this->assertGuest();
    }

    public function test_a_laravel_sign_in_writes_the_legacy_session_where_the_legacy_pages_read_it(): void
    {
        // The defaults used to differ (storage/framework/geoapi-sessions here, legacy/data/sessions
        // in the legacy app), so without the variable the legacy pages never saw the sign-in.
        $this->setEnv('GEOAPI_PLATFORM_SESSION_PATH', null);
        $dir = base_path('legacy/data/sessions');
        $before = glob($dir.'/sess_*') ?: [];

        event(new Login('web', User::query()->where('legacy_platform_user_id', 70)->firstOrFail(), false));

        $written = array_diff(glob($dir.'/sess_*') ?: [], $before);
        array_map('unlink', $written);
        $this->assertSame($dir, Session::path());
        $this->assertCount(1, $written);
    }

    public function test_logout_deletes_the_legacy_session_so_a_copied_cookie_stops_working(): void
    {
        $sid = $this->legacySession(60, 60);
        $this->withUnencryptedCookie('geoapi_platform', $sid)->get('/');
        $this->assertAuthenticated();

        $this->withUnencryptedCookie('geoapi_platform', $sid)->post('/logout');

        $this->assertFileDoesNotExist($this->dir.'/sess_'.$sid);
        $this->flushSession();
        auth()->forgetGuards();
        $this->withUnencryptedCookie('geoapi_platform', $sid)->get('/');
        $this->assertGuest();
    }

    public function test_a_bridge_sign_in_rotates_the_legacy_session_so_logout_revokes_every_copy(): void
    {
        $copied = $this->legacySession(60, 60);
        $response = $this->withUnencryptedCookie('geoapi_platform', $copied)->get('/');
        $this->assertAuthenticated();
        $rotated = $response->getCookie('geoapi_platform', false)?->getValue();
        $this->assertIsString($rotated);
        $this->assertNotSame($copied, $rotated);
        $this->assertFileDoesNotExist($this->dir.'/sess_'.$copied, 'the sid the bridge used must not stay valid');

        $this->withUnencryptedCookie('geoapi_platform', $rotated)->post('/logout');
        $this->assertFileDoesNotExist($this->dir.'/sess_'.$rotated);

        foreach ([$copied, $rotated] as $sid) {
            $this->flushSession();
            auth()->forgetGuards();
            $this->withUnencryptedCookie('geoapi_platform', $sid)->get('/');
            $this->assertGuest();
        }
    }
}
