<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Analysis;

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/** Apply one bounded redaction policy to diagnostic metadata. */
final class DiagnosticSanitizer
{
    private const MAX_DEPTH = 4;
    private const MAX_ENTRIES = 100;
    private const MAX_STRING_BYTES = 2048;
    private const REDACTED = '[redacted]';
    private const SENSITIVE_KEY = '/(?:password|passwd|passphrase|token|secret|authorization|cookie|session|api[_-]?key|csrf|nonce)/i';

    /**
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    public function sanitize(array $values, int $depth = 0): array
    {
        $result = [];
        $count = 0;
        foreach ($values as $key => $value) {
            if ($count >= self::MAX_ENTRIES) {
                $result['[truncated]'] = sprintf('%d additional entries omitted', count($values) - $count);

                break;
            }
            ++$count;

            $keyName = (string) $key;
            if ($this->isSensitiveKey($keyName)) {
                $result[$key] = self::REDACTED;

                continue;
            }
            if (is_string($value) && in_array(strtolower($keyName), ['url', 'uri'], true)) {
                $result[$key] = $this->sanitizeUrl($value);

                continue;
            }
            if (is_array($value)) {
                $result[$key] = $depth + 1 >= self::MAX_DEPTH
                    ? '[maximum depth reached]'
                    : $this->sanitize($value, $depth + 1);

                continue;
            }
            if (is_string($value)) {
                $result[$key] = $this->truncate($value);

                continue;
            }
            $result[$key] = is_scalar($value) || $value === null ? $value : get_debug_type($value);
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $cookies
     * @return array<array-key, string>
     */
    public function sanitizeCookies(array $cookies): array
    {
        $result = [];
        $count = 0;
        foreach ($cookies as $key => $value) {
            if ($count >= self::MAX_ENTRIES) {
                $result['[truncated]'] = sprintf('%d additional entries omitted', count($cookies) - $count);

                break;
            }
            ++$count;
            $result[$key] = self::REDACTED;
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $headers
     * @return array<array-key, mixed>
     */
    public function sanitizeHeaders(array $headers): array
    {
        return $this->sanitize($headers);
    }

    public function sanitizeUrl(string $url): string
    {
        $url = $this->truncate($url);
        $url = preg_replace('#^([a-z][a-z0-9+.-]*://)([^/@]+)@#i', '$1' . self::REDACTED . '@', $url) ?? $url;

        $fragment = '';
        $fragmentPosition = strpos($url, '#');
        if ($fragmentPosition !== false) {
            $fragment = substr($url, $fragmentPosition);
            $url = substr($url, 0, $fragmentPosition);
        }

        $queryPosition = strpos($url, '?');
        if ($queryPosition === false) {
            return $url . $fragment;
        }

        $base = substr($url, 0, $queryPosition);
        $query = substr($url, $queryPosition + 1);

        return $base . '?' . $this->sanitizeQuery($query) . $fragment;
    }

    private function sanitizeQuery(string $query): string
    {
        $parts = preg_split('/([&;])/', $query, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (! is_array($parts)) {
            return '';
        }

        foreach ($parts as $index => $part) {
            if ($part === '&' || $part === ';' || $part === '') {
                continue;
            }
            $pieces = explode('=', $part, 2);
            $rawKey = $pieces[0];
            $rawValue = $pieces[1] ?? null;
            $decodedKey = rawurldecode(str_replace('+', ' ', $rawKey));
            if ($this->isSensitiveKey($decodedKey)) {
                $parts[$index] = $rawKey . '=' . rawurlencode(self::REDACTED);

                continue;
            }
            if ($rawValue !== null) {
                $parts[$index] = $rawKey . '=' . $this->truncate($rawValue);
            }
        }

        return implode('', $parts);
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match(self::SENSITIVE_KEY, $key) === 1;
    }

    private function truncate(string $value): string
    {
        if (strlen($value) <= self::MAX_STRING_BYTES) {
            return $value;
        }

        $truncated = function_exists('mb_strcut')
            ? mb_strcut($value, 0, self::MAX_STRING_BYTES, 'UTF-8')
            : substr($value, 0, self::MAX_STRING_BYTES);

        return $truncated . '...';
    }
}
