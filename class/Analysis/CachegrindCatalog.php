<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Analysis;

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/** Secure, read-only catalog of Xdebug cachegrind output files. */
final class CachegrindCatalog
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim(trim($directory ?? (string) ini_get('xdebug.output_dir')), '/\\');
    }

    /** @return list<array{file: string, modified: int, size: int}> */
    public function listFiles(int $limit = 50): array
    {
        if ($this->directory === '' || !is_dir($this->directory) || !is_readable($this->directory)) {
            return [];
        }

        $files = [];
        try {
            foreach (new \FilesystemIterator($this->directory, \FilesystemIterator::SKIP_DOTS) as $item) {
                if (!$item instanceof \SplFileInfo
                    || !$item->isFile()
                    || $item->isLink()
                    || !self::isValidFilename($item->getFilename())) {
                    continue;
                }
                $files[] = [
                    'file' => $item->getFilename(),
                    'modified' => $item->getMTime(),
                    'size' => $item->getSize(),
                ];
            }
        } catch (\Throwable) {
            return [];
        }

        usort($files, static fn (array $left, array $right): int => $right['modified'] <=> $left['modified']);

        return array_slice($files, 0, max(1, min(200, $limit)));
    }

    public function resolve(string $filename): ?string
    {
        if (!self::isValidFilename($filename) || $this->directory === '') {
            return null;
        }

        $base = realpath($this->directory);
        $path = realpath($this->directory . DIRECTORY_SEPARATOR . $filename);
        if ($base === false || $path === false || is_link($path) || !is_file($path)) {
            return null;
        }

        $prefix = rtrim($base, '/\\') . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $prefix) ? $path : null;
    }

    /**
     * Remove expired Xdebug profiles from its configured output directory.
     *
     * Called only by the CSRF-protected module administration action.
     */
    public function purgeOlderThan(int $days): int
    {
        if ($days < 1 || $this->directory === '' || !is_dir($this->directory) || !is_readable($this->directory)) {
            return 0;
        }

        $cutoff = time() - ($days * 86400);
        $purged = 0;
        try {
            foreach (new \FilesystemIterator($this->directory, \FilesystemIterator::SKIP_DOTS) as $item) {
                if (!$item instanceof \SplFileInfo
                    || !$item->isFile()
                    || $item->isLink()
                    || $item->getMTime() >= $cutoff
                    || !self::isValidFilename($item->getFilename())) {
                    continue;
                }

                $path = $this->resolve($item->getFilename());
                if ($path !== null && self::removeFile($path)) {
                    ++$purged;
                }
            }
        } catch (\Throwable) {
            return $purged;
        }

        return $purged;
    }

    private static function removeFile(string $path): bool
    {
        set_error_handler(
            static function (int $severity, string $message, string $file, int $line): never {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }
        );
        try {
            return unlink($path);
        } catch (\Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }
    }

    private static function isValidFilename(string $filename): bool
    {
        return $filename === basename($filename)
            && !str_contains($filename, '..')
            && preg_match('/^cachegrind\.out\.[A-Za-z0-9._-]+$/D', $filename) === 1;
    }
}
