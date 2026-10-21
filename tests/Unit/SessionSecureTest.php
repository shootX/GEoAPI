<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SessionSecure;
use PHPUnit\Framework\TestCase;

final class SessionSecureTest extends TestCase
{
    public function test_an_unset_cookie_flag_is_secure_only_in_production(): void
    {
        $this->assertTrue(SessionSecure::resolve(null, 'production'));
        $this->assertFalse(SessionSecure::resolve(null, 'testing'));
        $this->assertFalse(SessionSecure::resolve(null, 'local'));
        $this->assertTrue(SessionSecure::resolve('', 'production'));
        $this->assertTrue(SessionSecure::resolve('   ', 'production'));
        $this->assertFalse(SessionSecure::resolve('', 'testing'));
        $this->assertFalse(SessionSecure::resolve('false', 'production'));
        $this->assertTrue(SessionSecure::resolve('true', 'testing'));
        $this->assertFalse(SessionSecure::resolve(false, 'production'));
        $this->assertTrue(SessionSecure::resolve(true, 'testing'));
    }
}
