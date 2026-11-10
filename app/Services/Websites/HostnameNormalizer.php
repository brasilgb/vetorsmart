<?php

namespace App\Services\Websites;

/**
 * Turns whatever arrived in a Host header or in a form into one canonical hostname,
 * or null when it is not a valid domain name. IP literals are never accepted.
 */
class HostnameNormalizer
{
    private const string PATTERN = '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

    public function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $host = strtolower(trim($raw));

        // Strip an explicit port and a trailing root dot.
        $host = preg_replace('/:\d{1,5}$/', '', $host) ?? '';
        $host = rtrim($host, '.');

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        return preg_match(self::PATTERN, $host) === 1 ? $host : null;
    }
}
