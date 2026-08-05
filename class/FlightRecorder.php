<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar;

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/** Small bounded JSON flight recorder for performance violations. */
final class FlightRecorder
{
    public function __construct(private readonly ?string $directory = null)
    {
    }

    /** @param array<string, mixed> $payload */
    public function record(string $requestId, array $payload, bool $violation, int $maxFiles = 30): bool
    {
        if (preg_match('/^[a-f0-9]{16}$/', $requestId) !== 1) {
            return false;
        }

        try {
            $dir = $this->directory();
            if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                return false;
            }
            // Directory-listing guard. The records carry request URIs, SQL and
            // timings, and this directory is only protected by .htaccess -- which
            // does nothing on nginx, IIS, or Apache with AllowOverride None. An
            // index.html is the one defence that works on all of them. Errors are
            // suppressed: failing to write it must not lose the record itself.
            if (! is_file($dir . '/index.html')) {
                @file_put_contents($dir . '/index.html', '<script>history.go(-1);</script>');
            }
            $file = sprintf('%s/%010d-%s-%s.json', $dir, time(), $violation ? 'v' : 'r', $requestId);
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if ($json === false || file_put_contents($file, $json, LOCK_EX) === false) {
                return false;
            }
            $this->prune($maxFiles);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array{file: string, created: int, violation: bool, request_id: string, bytes: int}> */
    public function listRecords(int $limit = 50): array
    {
        $matches = glob($this->directory() . '/*-[vr]-????????????????.json');
        $files = $matches !== false ? $matches : [];
        $records = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (preg_match('/^(\d{10})-([vr])-([a-f0-9]{16})\.json$/', $name, $m) !== 1) {
                continue;
            }
            // is_file() rather than a bare filesize(): glob() also matches a
            // directory or a dangling symlink sitting at a record's path, and
            // filesize() warns on both. This module collects PHP warnings, so
            // one raised here would land in the panel this class feeds. A
            // broken entry is still listed -- prune() has to see it to remove
            // it -- it simply reports no bytes.
            $records[] = ['file' => $name, 'created' => (int) $m[1], 'violation' => $m[2] === 'v', 'request_id' => $m[3], 'bytes' => is_file($file) ? (int) filesize($file) : 0];
        }
        usort($records, static fn (array $a, array $b): int => $b['created'] <=> $a['created']);

        return array_slice($records, 0, max(1, $limit));
    }

    /** @return array<string, mixed>|null */
    public function load(string $file): ?array
    {
        $file = basename($file);
        if (preg_match('/^\d{10}-[vr]-[a-f0-9]{16}\.json$/', $file) !== 1) {
            return null;
        }
        $path = $this->directory() . '/' . $file;
        if (! is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    private function directory(): string
    {
        return $this->directory !== null && $this->directory !== ''
            ? $this->directory
            : (defined('XOOPS_VAR_PATH') ? XOOPS_VAR_PATH . '/debugbar' : XOOPS_ROOT_PATH . '/cache/debugbar');
    }

    /**
     * Bring the directory back to the cap, in priority order.
     *
     * Iterating past the initial overflow set matters: if the oldest record
     * cannot be removed, stopping there leaves the directory over its cap while
     * perfectly removable records sit further down the list. The sort already
     * encodes the priority -- plain records before flagged ones, oldest first --
     * so continuing down it removes the next-best candidate rather than an
     * arbitrary one.
     *
     * Deliberately returns nothing. An earlier revision returned the shortfall,
     * but no caller read it and no test could reach it, so it promised a signal
     * that did not exist. It comes back the day a Diagnostics row consumes it.
     */
    private function prune(int $maxFiles): void
    {
        $records = $this->listRecords(PHP_INT_MAX);
        $overflow = count($records) - max(1, $maxFiles);
        if ($overflow <= 0) {
            return;
        }
        usort($records, static function (array $a, array $b): int {
            $violationOrder = $a['violation'] <=> $b['violation'];

            return $violationOrder !== 0 ? $violationOrder : ($a['created'] <=> $b['created']);
        });

        $removed = 0;
        foreach ($records as $record) {
            if ($removed >= $overflow) {
                return;
            }
            if ($this->removeFile($this->directory() . '/' . $record['file'])) {
                $removed++;
            }
        }
    }

    /**
     * Remove one record, reporting whether it is actually gone.
     *
     * The error handler stays: this module captures PHP warnings, so a warning
     * raised here would be collected into the very panel this class feeds.
     * Suppressing the warning is right; suppressing the RESULT as well was the
     * defect -- prune() could not tell a deletion from a no-op, so the retention
     * cap was advisory while record() went on reporting success.
     *
     * Survival is confirmed rather than inferred from unlink()'s return: a
     * directory sitting at a record's path is skipped by the is_file() guard
     * without unlink ever being called, and Windows can report a delete that
     * leaves the entry in place while a handle is open.
     *
     * is_link() is tested alongside both because is_file() and file_exists()
     * follow symlinks: a dangling link at a record's path would otherwise be
     * skipped by the guard AND report as gone, while glob() goes on listing it --
     * the same false success this method exists to prevent.
     */
    private function removeFile(string $path): bool
    {
        set_error_handler(static fn (): bool => true);

        try {
            if (is_file($path) || is_link($path)) {
                unlink($path);
            }
            clearstatcache(true, $path);

            return ! (file_exists($path) || is_link($path));
        } finally {
            restore_error_handler();
        }
    }
}
