<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class WidgetRetryTest extends TestCase
{
    public function test_widget_retries_429_503_and_network_errors_at_most_twice(): void
    {
        $node = trim((string) shell_exec('command -v node'));
        if ($node === '') {
            $this->markTestSkipped('node is not installed');
        }
        $script = dirname(__DIR__).'/js/widget-retry.cjs';
        exec(escapeshellarg($node).' '.escapeshellarg($script).' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
        $this->assertSame('ok', end($output));
    }
}
