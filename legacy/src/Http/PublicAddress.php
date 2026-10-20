<?php

declare(strict_types=1);

namespace Carapi\Http;

final class PublicAddress
{
    public static function isPublic(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return !self::ipv4Blocked($ip);
        }

        return !self::ipv6Blocked($ip);
    }

    public static function pin(string $host, string $ip): string
    {
        if (str_contains($ip, ':')) {
            return $host . ':443:[' . $ip . ']';
        }

        return $host . ':443:' . $ip;
    }

    private static function ipv4Blocked(string $ip): bool
    {
        $ranges = [
            ['0.0.0.0', 8],
            ['10.0.0.0', 8],
            ['100.64.0.0', 10],
            ['127.0.0.0', 8],
            ['169.254.0.0', 16],
            ['172.16.0.0', 12],
            ['192.0.0.0', 24],
            ['192.0.2.0', 24],
            ['192.168.0.0', 16],
            ['198.18.0.0', 15],
            ['198.51.100.0', 24],
            ['203.0.113.0', 24],
            ['224.0.0.0', 4],
            ['240.0.0.0', 4],
        ];
        foreach ($ranges as [$network, $bits]) {
            if (self::ipv4InCidr($ip, $network, $bits)) {
                return true;
            }
        }

        return false;
    }

    private static function ipv4InCidr(string $ip, string $network, int $bits): bool
    {
        $ipLong = ip2long($ip);
        $netLong = ip2long($network);
        if ($ipLong === false || $netLong === false || $bits < 0 || $bits > 32) {
            return true;
        }
        $ipLong &= 0xFFFFFFFF;
        $netLong &= 0xFFFFFFFF;
        $mask = $bits === 0 ? 0 : ((0xFFFFFFFF << (32 - $bits)) & 0xFFFFFFFF);

        return ($ipLong & $mask) === ($netLong & $mask);
    }

    private static function ipv6Blocked(string $ip): bool
    {
        $bin = inet_pton($ip);
        if ($bin === false || strlen($bin) !== 16) {
            return true;
        }
        if (substr($bin, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff") {
            return true;
        }
        $ranges = [
            ['::', 128],
            ['::1', 128],
            ['fc00::', 7],
            ['fe80::', 10],
            ['ff00::', 8],
            ['2001:db8::', 32],
            ['2002::', 16],
            ['64:ff9b::', 96],
            ['64:ff9b:1::', 48],
        ];
        foreach ($ranges as [$network, $bits]) {
            if (self::ipv6InCidr($bin, $network, $bits)) {
                return true;
            }
        }

        return false;
    }

    private static function ipv6InCidr(string $ipBin, string $network, int $bits): bool
    {
        $net = inet_pton($network);
        if ($net === false || $bits < 0 || $bits > 128) {
            return true;
        }
        $bytes = intdiv($bits, 8);
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($net, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($ipBin[$bytes]) & $mask) === (ord($net[$bytes]) & $mask);
    }
}
