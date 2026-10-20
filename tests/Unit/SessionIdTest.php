<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carapi\Platform\Session;
use PHPUnit\Framework\TestCase;

final class SessionIdTest extends TestCase
{
    public function test_only_a_32_character_hex_session_id_is_accepted(): void
    {
        $sid = str_repeat('ab', 16);

        $this->assertSame($sid, Session::acceptSessionId($sid, null));
        $this->assertSame($sid, Session::acceptSessionId(str_repeat('A', 180), $sid));
        $this->assertNull(Session::acceptSessionId(str_repeat('a', 180), null));
        $this->assertNull(Session::acceptSessionId('not-hex-and-not-32-chars!!', null));
    }
}
