<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Analysis;

final class LogCatalog
{
    /**
     * Key for the plain-text XOOPS core debug log, used as both the `source` label and
     * the `file` selector. Declared once so the catalog, the resolver and the viewer
     * cannot drift apart.
     */
    public const SOURCE_CORE = 'core';

    /**
     * Filename the XOOPS 2.7.3 core file logger writes, relative to the logs directory.
     * Fixed by convention rather than configurable: the core log has one standard
     * location, so the viewer needs no setup to find it.
     *
     * debug.php does technically accept another plain "*.log" name. A site that renames
     * its log will simply not see it listed here -- and one that renames it to
     * "xoops.log" would see it listed as a MONOLOG file and parsed as JSON lines, because
     * that name matches isMonologName(). Both are reasons to leave the name alone.
     */
    public const CORE_LOG_FILENAME = 'debug.log';

    public function __construct(
        private readonly string $monologDirectory,
        private readonly ?string $coreLogFile = null,
        private readonly int $maximumBytes = 262144
    ) {
    }

    /** @return list<array{source:string,file:string,modified:int,size:int}> */
    public function listFiles(): array
    {
        $files = [];
        $directory = rtrim($this->monologDirectory, '/\\');
        if ($directory !== '' && is_dir($directory)) {
            $paths = glob($directory . '/xoops*.log');
            foreach ($paths !== false ? $paths : [] as $path) {
                $name = basename($path);
                if (! $this->isMonologName($name) || ! is_file($path)) {
                    continue;
                }
                $files[] = ['source' => 'monolog', 'file' => $name, 'modified' => (int) filemtime($path), 'size' => (int) filesize($path)];
            }
        }
        if ($this->coreLogFile !== null && is_file($this->coreLogFile)) {
            $files[] = ['source' => self::SOURCE_CORE, 'file' => self::SOURCE_CORE, 'modified' => (int) filemtime($this->coreLogFile), 'size' => (int) filesize($this->coreLogFile)];
        }
        usort($files, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return $files;
    }

    public function read(string $file): ?string
    {
        $path = $this->resolve($file);
        if ($path === null) {
            return null;
        }
        $size = (int) filesize($path);
        $length = max(1, min($this->maximumBytes, $size));
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            if ($size > $length) {
                fseek($handle, -$length, SEEK_END);
            }
            $contents = fread($handle, $length);

            return $contents === false ? null : $contents;
        } finally {
            fclose($handle);
        }
    }

    private function resolve(string $file): ?string
    {
        if ($file === self::SOURCE_CORE) {
            if ($this->coreLogFile === null || ! is_file($this->coreLogFile)) {
                return null;
            }
            // Held to the same containment as the Monolog branch below. The path is built
            // by the caller rather than taken from the request, so this is not reachable
            // from the browser -- but a symlink planted at logs/debug.log would otherwise
            // let a read escape the log directory, and the core file logger refuses to
            // WRITE through a symlink for exactly that reason. The reader should not be
            // more trusting than the writer.
            $real = realpath($this->coreLogFile);
            if ($real === false) {
                return null;
            }
            $directory = $this->monologDirectory === '' ? false : realpath($this->monologDirectory);

            return $directory !== false && str_starts_with($real, $directory . DIRECTORY_SEPARATOR)
                ? $real
                : null;
        }
        if ($file !== basename($file)) {
            return null;
        }
        if (! $this->isMonologName($file)) {
            return null;
        }
        if ($this->monologDirectory === '') {
            return null;
        }
        $directory = realpath($this->monologDirectory);
        $path = realpath(rtrim($this->monologDirectory, '/\\') . '/' . $file);
        if ($directory === false || $path === false || ! str_starts_with($path, $directory . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $path;
    }

    private function isMonologName(string $file): bool
    {
        return preg_match('/^xoops(?:-\d{4}-\d{2}-\d{2})?\.log$/D', $file) === 1;
    }
}
