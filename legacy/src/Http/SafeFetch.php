<?php

declare(strict_types=1);

namespace Carapi\Http;

final class SafeFetch
{
    public const HTML_MAX = 2000000;
    public const IMAGE_MAX = 1500000;
    public const TIMEOUT = 8;
    public const MAX_REDIRECTS = 3;

    /** @param list<string> $allowedHosts */
    public function __construct(
        private readonly array $allowedHosts,
        private readonly mixed $dns = null,
        private readonly ?FetchTransport $transport = null,
    ) {
    }

    public function fetch(string $url, int $maxBytes): ?string
    {
        $current = $url;
        $transport = $this->transport ?? new CurlFetchTransport();
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $host = self::allowedHost($current, $this->allowedHosts);
            if ($host === null) {
                return null;
            }
            $ips = $this->lookup($host);
            if ($ips === null) {
                return null;
            }
            $result = $transport->execute($current, [PublicAddress::pin($host, $ips[0])], $maxBytes, self::TIMEOUT);
            if ($result['failed'] || strlen($result['body']) > $maxBytes) {
                return null;
            }
            if ($result['status'] >= 200 && $result['status'] < 300) {
                return $result['body'] !== '' ? $result['body'] : null;
            }
            if (!in_array($result['status'], [301, 302, 303, 307, 308], true)) {
                return null;
            }
            if ($hop === self::MAX_REDIRECTS) {
                return null;
            }
            $next = self::redirectTarget($current, (string) $result['location']);
            if ($next === null) {
                return null;
            }
            $current = $next;
        }

        return null;
    }

    /** @param list<string> $allowedHosts */
    public static function allowedHost(string $url, array $allowedHosts): ?string
    {
        if ($url === '' || str_contains($url, '@') || str_contains($url, '\\') || str_contains($url, "\0") || preg_match('/\s/', $url) === 1) {
            return null;
        }
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        if (($parts['port'] ?? 443) !== 443) {
            return null;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || str_ends_with($host, '.') || !preg_match('/^[a-z0-9.-]+$/', $host)) {
            return null;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }
        $allowed = [];
        foreach ($allowedHosts as $item) {
            $allowed[] = strtolower($item);
        }
        if (!in_array($host, $allowed, true)) {
            return null;
        }

        return $host;
    }

    public static function redirectTarget(string $current, string $location): ?string
    {
        $location = trim($location);
        if ($location === '' || str_contains($location, "\r") || str_contains($location, "\n") || str_contains($location, '\\')) {
            return null;
        }
        if (str_starts_with($location, 'https://')) {
            return $location;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location) === 1 || str_starts_with($location, '//')) {
            return null;
        }
        $base = parse_url($current);
        if (!is_array($base) || strtolower((string) ($base['scheme'] ?? '')) !== 'https') {
            return null;
        }
        $host = strtolower((string) ($base['host'] ?? ''));
        if ($host === '') {
            return null;
        }
        $query = '';
        $path = $location;
        if (str_contains($location, '?')) {
            [$path, $query] = explode('?', $location, 2);
            $query = '?' . $query;
        }
        if (!str_starts_with($path, '/')) {
            $basePath = (string) ($base['path'] ?? '/');
            $slash = strrpos($basePath, '/');
            $dir = $slash === false ? '' : substr($basePath, 0, $slash);
            $path = $dir . '/' . $path;
        }

        return 'https://' . $host . $path . $query;
    }

    /** @return list<string>|null */
    private function lookup(string $host): ?array
    {
        $ips = $this->dns !== null ? ($this->dns)($host) : self::dns($host);
        if (!is_array($ips) || $ips === []) {
            return null;
        }
        $clean = [];
        foreach ($ips as $ip) {
            if (!is_string($ip) || !PublicAddress::isPublic($ip)) {
                return null;
            }
            $clean[] = $ip;
        }

        return $clean;
    }

    /** @return list<string> */
    public static function dns(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (!is_array($records)) {
            return [];
        }
        $ips = [];
        foreach ($records as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (isset($row['ip']) && is_string($row['ip'])) {
                $ips[] = $row['ip'];
            }
            if (isset($row['ipv6']) && is_string($row['ipv6'])) {
                $ips[] = $row['ipv6'];
            }
        }

        return $ips;
    }
}
