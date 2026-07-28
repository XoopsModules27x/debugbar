<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Analysis;

/**
 * DebugBar Module - Page Asset Scanner
 *
 * Scans rendered HTML for script/stylesheet tags, sums local asset sizes,
 * and detects the classic ecosystem failure of two different copies of the
 * same JS runtime (jQuery, Alpine, htmx, ...) on one page.
 *
 * @category  Module
 * @package   debugbar
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Pure scanner over an HTML string — no I/O beyond optional filesize lookups.
 */
final class AssetScanner
{
    /**
     * Runtime name => filename detection regex (applied to the URL path basename).
     */
    private const RUNTIME_PATTERNS = [
        'jquery' => '/^jquery(?:[-.]\d[\d.]*)?(?:\.slim)?(?:\.min)?\.js$/i',
        'alpine' => '/^alpine(?:js)?(?:[-.]\d[\d.]*)?(?:\.min)?\.js$/i',
        'htmx' => '/^htmx(?:[-.]\d[\d.]*)?(?:\.min)?\.js$/i',
        'vue' => '/^vue(?:[-.]\d[\d.]*)?(?:\.global)?(?:\.prod)?(?:\.min)?\.js$/i',
        'react' => '/^react(?:-dom)?(?:[-.]\d[\d.]*)?(?:\.production)?(?:\.min)?\.js$/i',
    ];

    /**
     * Scan rendered HTML for asset facts.
     *
     * @param string $html         the rendered page
     * @param string $rootUrl      site URL (XOOPS_URL) used to resolve local asset paths
     * @param string $rootPath     filesystem root (XOOPS_ROOT_PATH) for size lookups
     * @param bool   $includeSizes whether to stat local files for byte sizes
     *
     * @return array{
     *     script_count: int,
     *     style_count: int,
     *     script_bytes: int,
     *     style_bytes: int,
     *     duplicate_runtimes: array<string, string[]>
     * }
     */
    public static function scan(string $html, string $rootUrl = '', string $rootPath = '', bool $includeSizes = true): array
    {
        $scripts = [];
        $scriptMatchCount = preg_match_all('/<script\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i', $html, $matches);
        if (false !== $scriptMatchCount && $scriptMatchCount > 0) {
            $scripts = $matches[1];
        }

        $styles = [];
        $styleMatchCount = preg_match_all('/<link\b[^>]*\brel\s*=\s*["\']stylesheet["\'][^>]*\bhref\s*=\s*["\']([^"\']+)["\']/i', $html, $matches);
        if (false !== $styleMatchCount && $styleMatchCount > 0) {
            $styles = $matches[1];
        }
        // href-before-rel attribute order
        $altStyleMatchCount = preg_match_all('/<link\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\'][^>]*\brel\s*=\s*["\']stylesheet["\']/i', $html, $matches);
        if (false !== $altStyleMatchCount && $altStyleMatchCount > 0) {
            $styles = array_merge($styles, $matches[1]);
        }
        $styles = array_values(array_unique($styles));

        return [
            'script_count' => count($scripts),
            'style_count' => count($styles),
            'script_bytes' => $includeSizes ? self::sumLocalBytes($scripts, $rootUrl, $rootPath) : 0,
            'style_bytes' => $includeSizes ? self::sumLocalBytes($styles, $rootUrl, $rootPath) : 0,
            'duplicate_runtimes' => self::findDuplicateRuntimes($scripts),
        ];
    }

    /**
     * Detect runtimes included from more than one distinct URL.
     *
     * @param string[] $scriptUrls script src values
     *
     * @return array<string, string[]> runtime name => distinct URLs (only when > 1)
     */
    public static function findDuplicateRuntimes(array $scriptUrls): array
    {
        /** @var array<string, array<string, true>> $seen */
        $seen = [];
        foreach ($scriptUrls as $url) {
            $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
            if ('' === $path) {
                continue;
            }
            $basename = basename($path);
            foreach (self::RUNTIME_PATTERNS as $runtime => $pattern) {
                if (1 === preg_match($pattern, $basename)) {
                    $seen[$runtime][$path] = true;

                    break;
                }
            }
        }

        $duplicates = [];
        foreach ($seen as $runtime => $paths) {
            if (count($paths) > 1) {
                $duplicates[$runtime] = array_keys($paths);
            }
        }

        return $duplicates;
    }

    /**
     * Sum on-disk sizes for assets served from this site.
     *
     * @param string[] $urls     asset URLs
     * @param string   $rootUrl  site URL prefix identifying local assets
     * @param string   $rootPath filesystem root the URL prefix maps to
     *
     * @return int total bytes (0 when roots are not provided)
     */
    private static function sumLocalBytes(array $urls, string $rootUrl, string $rootPath): int
    {
        if ('' === $rootUrl || '' === $rootPath) {
            return 0;
        }

        $bytes = 0;
        $realRoot = realpath($rootPath);
        if (false === $realRoot) {
            return 0;
        }

        foreach (array_unique($urls) as $url) {
            if (0 !== strpos($url, $rootUrl)) {
                continue;   // external or relative — skip
            }
            $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
            $base = (string) (parse_url($rootUrl, PHP_URL_PATH) ?? '');
            if ('' !== $base && 0 === strpos($path, $base)) {
                $path = substr($path, strlen($base));
            }
            $candidate = realpath($rootPath . '/' . ltrim($path, '/'));
            // realpath containment check keeps us inside the web root
            if (false !== $candidate && 0 === strpos($candidate, $realRoot) && is_file($candidate)) {
                $bytes += (int) filesize($candidate);
            }
        }

        return $bytes;
    }
}
