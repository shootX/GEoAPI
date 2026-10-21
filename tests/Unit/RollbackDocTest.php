<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RollbackDocTest extends TestCase
{
    public function test_rollback_notes_exist_and_do_not_carry_a_secret(): void
    {
        $path = dirname(__DIR__, 2).'/docs/ROLLBACK.md';
        $text = (string) file_get_contents($path);

        $this->assertNotSame('', $text);
        $this->assertStringContainsString('was not executed', $text);
        $this->assertStringContainsString('/ready', $text);
        $this->assertStringNotContainsString('password=', $text);
        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $text);
        $this->assertStringNotContainsString('BEGIN RSA PRIVATE KEY', $text);
    }
}
