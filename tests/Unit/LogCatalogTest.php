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
}
