<?php

declare(strict_types=1);

namespace Carapi\Http;

final class CurlFetchTransport implements FetchTransport
{
    public function execute(string $url, array $resolve, int $maxBytes, int $timeoutSeconds): array
    {
        $failed = ['status' => 0, 'location' => null, 'body' => '', 'failed' => true];
        if (!function_exists('curl_init')) {
            return $failed;
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return $failed;
        }

        $body = '';
        $location = null;
        $aborted = false;
        foreach (self::options($resolve, $timeoutSeconds) as $option => $value) {
            curl_setopt($ch, $option, $value);
        }
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($ch, string $chunk) use (&$body, $maxBytes, &$aborted): int {
            if (strlen($body) + strlen($chunk) > $maxBytes) {
                $aborted = true;

                return 0;
            }
            $body .= $chunk;

            return strlen($chunk);
        });
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($ch, string $header) use (&$location): int {
            if (preg_match('/^Location:\s*(\S+)/i', $header, $match) === 1) {
                $location = $match[1];
            }

            return strlen($header);
        });

        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($ok !== true || $aborted) {
            return $failed;
        }

        return ['status' => $status, 'location' => $location, 'body' => $body, 'failed' => false];
    }

    /**
     * @param list<string> $resolve
     * @return array<int, mixed>
     */
    public static function options(array $resolve, int $timeoutSeconds): array
    {
        $timeout = max(1, $timeoutSeconds);

        return [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_FORBID_REUSE => true,
            CURLOPT_DNS_CACHE_TIMEOUT => 0,
            CURLOPT_RESOLVE => $resolve,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(4, $timeout),
            CURLOPT_HTTPGET => true,
            CURLOPT_UNRESTRICTED_AUTH => false,
            CURLOPT_USERAGENT => 'GeoAPI/1.0',
        ];
    }
}
