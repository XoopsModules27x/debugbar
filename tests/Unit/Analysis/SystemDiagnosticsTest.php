<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit\Analysis;

use PHPUnit\Framework\TestCase;
use XoopsModules\Debugbar\Analysis\SystemDiagnostics;

if (! defined('XOOPS_ROOT_PATH')) {
    define('XOOPS_ROOT_PATH', dirname(__DIR__, 3));
}

final class SystemDiagnosticsTest extends TestCase
{
    private string $root;

    private string $var;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/debugbar-diag-root-' . bin2hex(random_bytes(6));
        $this->var = sys_get_temp_dir() . '/debugbar-diag-var-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->root, 0777, true));
        self::assertTrue(mkdir($this->var, 0777, true));
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
        $this->rmrf($this->var);
    }

    private function rmrf(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items === false ? [] : $items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rmrf($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * @param array<string, list<array{id: string, value: string, status: string, detail: string}>> $report
     * @return array{id: string, value: string, status: string, detail: string}|null
     */
    private function findRow(array $report, string $section, string $id): ?array
    {
        foreach ($report[$section] as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    public function testExplainStashRowReportsMissingWhenDirectoryAbsent(): void
    {
        $diagnostics = new SystemDiagnostics($this->root, $this->var);
        $report = $diagnostics->collect([]);
        $row = $this->findRow($report, 'tools', 'explain_stash');

        self::assertNotNull($row);
        self::assertSame('Missing', $row['value']);
        self::assertSame('info', $row['status']);
    }

    public function testExplainStashRowReportsReadyWithQueryCount(): void
    {
        $stashDir = $this->var . '/caches/debugbar_explain';
        self::assertTrue(mkdir($stashDir, 0777, true));
        file_put_contents($stashDir . '/abc123.json', '{}');
        file_put_contents($stashDir . '/def456.json', '{}');

        $diagnostics = new SystemDiagnostics($this->root, $this->var);
        $report = $diagnostics->collect([]);
        $row = $this->findRow($report, 'tools', 'explain_stash');

        self::assertNotNull($row);
        self::assertSame('Ready', $row['value']);
        self::assertSame('ok', $row['status']);
        self::assertSame('2 cached queries', $row['detail']);
    }

    public function testExplainStashRowSingularWording(): void
    {
        $stashDir = $this->var . '/caches/debugbar_explain';
        self::assertTrue(mkdir($stashDir, 0777, true));
        file_put_contents($stashDir . '/abc123.json', '{}');

        $diagnostics = new SystemDiagnostics($this->root, $this->var);
        $report = $diagnostics->collect([]);
        $row = $this->findRow($report, 'tools', 'explain_stash');

        self::assertNotNull($row);
        self::assertSame('1 cached query', $row['detail']);
    }

    public function testWritableStorageRowsReflectFilesystemState(): void
    {
        mkdir($this->var . '/logs', 0777, true);

        $diagnostics = new SystemDiagnostics($this->root, $this->var);
        $report = $diagnostics->collect([]);
        $logsRow = $this->findRow($report, 'storage', 'logs');
        $cachesRow = $this->findRow($report, 'storage', 'caches');

        self::assertNotNull($logsRow);
        self::assertNotNull($cachesRow);
        self::assertSame('Writable', $logsRow['value']);
        self::assertSame('ok', $logsRow['status']);
        self::assertSame('Missing', $cachesRow['value']);
        self::assertSame('warning', $cachesRow['status']);
    }

    public function testDebugModeRowReflectsConfig(): void
    {
        $diagnostics = new SystemDiagnostics($this->root, $this->var);

        $enabled = $this->findRow($diagnostics->collect(['debug_mode' => 1]), 'runtime', 'xoops_debug');
        $disabled = $this->findRow($diagnostics->collect(['debug_mode' => 0]), 'runtime', 'xoops_debug');

        self::assertNotNull($enabled);
        self::assertNotNull($disabled);
        self::assertSame('Enabled', $enabled['value']);
        self::assertSame('warning', $enabled['status']);
        self::assertSame('Disabled', $disabled['value']);
        self::assertSame('ok', $disabled['status']);
    }

    public function testUnconfiguredThemeReportsNotConfigured(): void
    {
        $diagnostics = new SystemDiagnostics($this->root, $this->var);
        $report = $diagnostics->collect([]);
        $frontTheme = $this->findRow($report, 'themes', 'front_theme');

        self::assertNotNull($frontTheme);
        self::assertSame('Not configured', $frontTheme['value']);
        self::assertSame('warning', $frontTheme['status']);
    }

    public function testCollectReturnsAllExpectedSections(): void
    {
        $diagnostics = new SystemDiagnostics($this->root, $this->var);
        $report = $diagnostics->collect([]);

        self::assertSame(['runtime', 'themes', 'tools', 'storage', 'theme_system'], array_keys($report));
    }
}
