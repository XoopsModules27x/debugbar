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
        if (!preg_match('/^[a-f0-9]{16}$/', $requestId)) {
            return false;
        }
        try {
            $dir = $this->directory();
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                return false;
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
        $files = glob($this->directory() . '/*-[vr]-????????????????.json') ?: [];
        $records = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (!preg_match('/^(\d{10})-([vr])-([a-f0-9]{16})\.json$/', $name, $m)) {
                continue;
            }
            $records[] = ['file' => $name, 'created' => (int) $m[1], 'violation' => $m[2] === 'v', 'request_id' => $m[3], 'bytes' => (int) filesize($file)];
        }
        usort($records, static fn(array $a, array $b): int => $b['created'] <=> $a['created']);
        return array_slice($records, 0, max(1, $limit));
    }

    /** @return array<string, mixed>|null */
    public function load(string $file): ?array
    {
        $file = basename($file);
        if (!preg_match('/^\d{10}-[vr]-[a-f0-9]{16}\.json$/', $file)) {
            return null;
        }
        $path = $this->directory() . '/' . $file;
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    private function directory(): string
    {
        return $this->directory ?: (defined('XOOPS_VAR_PATH') ? XOOPS_VAR_PATH . '/debugbar' : XOOPS_ROOT_PATH . '/cache/debugbar');
    }

    private function prune(int $maxFiles): void
    {
        $records = $this->listRecords(PHP_INT_MAX);
        if (count($records) <= $maxFiles) {
            return;
        }
        usort($records, static fn(array $a, array $b): int => ($a['violation'] <=> $b['violation']) ?: ($a['created'] <=> $b['created']));
        foreach (array_slice($records, 0, count($records) - max(1, $maxFiles)) as $record) {
            @unlink($this->directory() . '/' . $record['file']);
        }
    }
}
