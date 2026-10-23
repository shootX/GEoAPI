<?php

declare(strict_types=1);

namespace Geoapi\Http;

final class FileRateLimiter
{
    public function __construct(
        private readonly string $dir,
        private readonly int $limit = 30,
        private readonly int $window = 60,
    ) {
    }

    /** @return array{allowed:bool,retry_after:int} */
    public function hit(string $ip, int $now): array
    {
        $closed = ['allowed' => false, 'retry_after' => $this->window];
        if ($this->limit < 1 || $this->window < 1 || !$this->ready()) {
            return $closed;
        }
        $path = $this->dir . '/' . hash('sha256', $this->bucketKey($ip));
        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return $closed;
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                return $closed;
            }
            $raw = stream_get_contents($fp);
            $start = $now;
            $count = 0;
            if (is_string($raw) && preg_match('/^([0-9]+) ([0-9]+)$/', trim($raw), $match) === 1) {
                $start = (int) $match[1];
                $count = (int) $match[2];
                if (($now - $start) >= $this->window) {
                    $start = $now;
                    $count = 0;
                }
            }
            if ($count >= $this->limit) {
                return ['allowed' => false, 'retry_after' => max(1, $this->window - ($now - $start))];
            }
            $count++;
            if (!rewind($fp) || !ftruncate($fp, 0)) {
                return $closed;
            }
            $written = fwrite($fp, $start . ' ' . $count);
            if ($written === false || !fflush($fp)) {
                return $closed;
            }

            return ['allowed' => true, 'retry_after' => 0];
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private function bucketKey(string $ip): string
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'invalid';
    }

    private function ready(): bool
    {
        if (is_dir($this->dir)) {
            return is_writable($this->dir);
        }

        return @mkdir($this->dir, 0750, true) && is_dir($this->dir) && is_writable($this->dir);
    }
}
