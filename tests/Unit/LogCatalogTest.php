<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit;

use PHPUnit\Framework\TestCase;
use XoopsModules\Debugbar\Analysis\LogCatalog;

if (! defined('XOOPS_ROOT_PATH')) {
    define('XOOPS_ROOT_PATH', dirname(__DIR__, 2));
}

final class LogCatalogTest extends TestCase
{
    public function testEmptyMonologDirectoryDoesNotHideCoreLogOrResolveRootFiles(): void
    {
        $directory = sys_get_temp_dir() . '/debugbar-log-catalog-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($directory));
        // The 2.7.3 core file logger writes debug.log. This slot used to hold the
        // pre-2.7.3 /log/log.txt that it replaced.
        $coreLogFile = $directory . '/debug.log';
        self::assertNotFalse(file_put_contents($coreLogFile, "core entry\n"));

        try {
            $catalog = new LogCatalog($directory, $coreLogFile);
            $files = $catalog->listFiles();

            self::assertCount(1, $files);
            self::assertSame('core', $files[0]['source']);
            self::assertSame("core entry\n", $catalog->read('core'));
            self::assertNull($catalog->read('xoops.log'));
            // The retired key must not still resolve.
            self::assertNull($catalog->read('legacy'));
        } finally {
            if (is_file($coreLogFile)) {
                self::assertTrue(unlink($coreLogFile));
            }
            if (is_dir($directory)) {
                self::assertTrue(rmdir($directory));
            }
        }
    }

    /**
     * Generic Monolog file listing/reading behavior — ported from the
     * upstream Analysis/LogCatalogTest.php coverage, which otherwise
     * exercises a `legacy` fallback that this core-log-based build does not
     * have (see testEmptyMonologDirectoryDoesNotHideCoreLogOrResolveRootFiles
     * above for the equivalent 'core' coverage).
     */
    private string $monologDirectory;

    protected function setUp(): void
    {
        $this->monologDirectory = sys_get_temp_dir() . '/debugbar-log-catalog-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->monologDirectory));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->monologDirectory)) {
            $files = glob($this->monologDirectory . '/*');
            foreach (false !== $files ? $files : [] as $file) {
                @unlink($file);
            }
            @rmdir($this->monologDirectory);
        }
    }

    public function testListsMonologFilesNewestFirst(): void
    {
        file_put_contents($this->monologDirectory . '/xoops-2026-01-01.log', 'old');
        touch($this->monologDirectory . '/xoops-2026-01-01.log', 1000);
        file_put_contents($this->monologDirectory . '/xoops-2026-01-02.log', 'new');
        touch($this->monologDirectory . '/xoops-2026-01-02.log', 2000);

        $catalog = new LogCatalog($this->monologDirectory);
        $files = $catalog->listFiles();

        self::assertCount(2, $files);
        self::assertSame('xoops-2026-01-02.log', $files[0]['file']);
        self::assertSame('xoops-2026-01-01.log', $files[1]['file']);
    }

    public function testIgnoresNonMatchingFileNames(): void
    {
        // No '../evil.log' fixture here: it resolved to sys_get_temp_dir(),
        // which tearDown() does not clean and which is shared, so the run both
        // leaked a file and could clobber someone else's. Traversal has its own
        // coverage in testReadRejectsPathTraversal() below.
        file_put_contents($this->monologDirectory . '/xoops-2026-01-01.log', 'ok');
        file_put_contents($this->monologDirectory . '/not-a-log.txt', 'nope');

        $catalog = new LogCatalog($this->monologDirectory);
        $files = $catalog->listFiles();

        self::assertCount(1, $files);
        self::assertSame('xoops-2026-01-01.log', $files[0]['file']);
    }

    public function testReadRejectsPathTraversal(): void
    {
        file_put_contents($this->monologDirectory . '/xoops-2026-01-01.log', 'ok');

        $catalog = new LogCatalog($this->monologDirectory);

        self::assertNull($catalog->read('../secret.txt'));
        self::assertNull($catalog->read('..%2f..%2fsecret.txt'));
    }

    public function testReadReturnsOnlyTailWhenExceedingMaximumBytes(): void
    {
        $content = str_repeat('a', 100) . str_repeat('b', 50);
        file_put_contents($this->monologDirectory . '/xoops-2026-01-01.log', $content);

        $catalog = new LogCatalog($this->monologDirectory, null, 50);
        $tail = $catalog->read('xoops-2026-01-01.log');

        self::assertSame(str_repeat('b', 50), $tail);
    }

    public function testReadReturnsNullForUnknownFile(): void
    {
        $catalog = new LogCatalog($this->monologDirectory);

        self::assertNull($catalog->read('xoops-2099-01-01.log'));
    }
}
